<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Finance\Domain\Actions\GenerateStatementAction;
use Modules\Finance\Domain\DataObjects\GenerateStatementData;
use Modules\Finance\Domain\DataObjects\StatementLine;
use Modules\Finance\Models\Invoice;
use Modules\People\Domain\Support\LinkedLearners;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Guardian;
use Modules\People\Models\StudentGuardian;

/**
 * `/api/v1/finance/*` for guardians (Volume 1 §9.3). Every figure is the ledger's own stored
 * invoice amount returned as a `Money` object — the client never adds or computes money
 * (§9.4 rule 2). A balance is only included for a learner whose link carries
 * `may_view_full_balance`; for any other link the field is absent from the response, not
 * hidden by the client. A voided invoice never counts towards a balance.
 */
final class GuardianFinanceController
{
    public function balances(Request $request, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok($linked->forUser($user)->map(function (StudentGuardian $link): array {
            $row = ['student_id' => $link->student->ulid, 'admission_number' => $link->student->admission_number];

            if ($link->may_view_full_balance) {
                $row['balances'] = $this->balancesFor($link->student_id);
            }

            return $row;
        })->values()->all());
    }

    public function invoices(Request $request, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['student' => ['required', 'string', 'max:40'], 'per_page' => ['nullable', 'integer'], 'page' => ['nullable', 'integer']]);

        $link = $linked->linkFor($user, (string) $request->query('student'));
        abort_if($link === null, 404);

        $page = Invoice::query()
            ->where('student_id', $link->student_id)
            ->where('status', '!=', 'voided')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(ApiResponse::perPage($request->integer('per_page') ?: null));

        return ApiResponse::page($page->getCollection()->map(fn (Invoice $invoice): array => $this->invoice($invoice, $link->may_view_full_balance))->values()->all(), $page);
    }

    public function showInvoice(Request $request, string $invoice, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $found = Invoice::query()->where('ulid', $invoice)->where('status', '!=', 'voided')->first();

        $link = $found === null ? null : $linked->forUser($user)->first(fn (StudentGuardian $l): bool => $l->student_id === $found->student_id);
        abort_if($found === null || $link === null, 404);

        return ApiResponse::ok($this->invoice($found->load('lines'), $link->may_view_full_balance, true));
    }

    /**
     * `GET /api/v1/me/liabilities` (Book C PPL-03 §8) — what this guardian is responsible for.
     */
    public function liabilities(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $guardian = Guardian::query()->where('user_id', $user->id)->first();
        abort_if($guardian === null, 404);

        $liabilities = FeeLiability::query()->where('guardian_id', $guardian->id)->where('is_active', true)->with('student')->get();

        return ApiResponse::ok($liabilities->map(fn (FeeLiability $liability): array => [
            'student_id' => $liability->student?->ulid,
            'admission_number' => $liability->student?->admission_number,
            'component_id' => $liability->component_id,
            'share_type' => $liability->share_type,
            'share_percent' => $liability->share_percent,
            'share_amount' => $liability->share_amount_minor === null ? null : Money::of($liability->share_amount_minor, Currency::from($liability->currency))->jsonSerialize(),
            'priority' => $liability->priority,
            'effective_from' => $liability->effective_from->toDateString(),
            'effective_to' => $liability->effective_to?->toDateString(),
        ])->values()->all());
    }

    /**
     * `GET /api/v1/me/statement` (Book C PPL-03 §8) — own liability only. `journal_lines` are
     * always keyed by the student's own subledger (billing attaches to a learner, never a
     * guardian directly — confirmed: every other `subledger_type` reference in this codebase is
     * `'student'`), so this reads one linked learner's statement at a time rather than a single
     * guardian-keyed one that doesn't exist in the ledger. Requires `may_view_full_balance`, the
     * same gate every other balance-bearing guardian endpoint already uses.
     */
    public function statement(Request $request, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'student' => ['required', 'string', 'max:40'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $link = $linked->linkFor($user, $data['student']);
        abort_if($link === null || ! $link->may_view_full_balance, 404);

        $currency = $data['currency'] ?? $link->student->school->base_currency;

        $statement = app(GenerateStatementAction::class)->execute(new GenerateStatementData(
            schoolId: $link->student->school_id,
            subledgerType: 'student',
            subledgerId: $link->student_id,
            currency: $currency,
            from: Carbon::parse($data['from']),
            to: Carbon::parse($data['to']),
        ));

        $currencyEnum = Currency::from($currency);

        return ApiResponse::ok([
            'currency' => $currency,
            'opening_balance' => Money::of($statement->openingBalanceMinor, $currencyEnum)->jsonSerialize(),
            'closing_balance' => Money::of($statement->closingBalanceMinor, $currencyEnum)->jsonSerialize(),
            'lines' => array_map(fn (StatementLine $line): array => [
                'effective_at' => $line->effectiveAt,
                'journal_number' => $line->journalNumber,
                'narration' => $line->narration,
                'direction' => $line->direction,
                'amount' => Money::of($line->amountMinor, $currencyEnum)->jsonSerialize(),
                'running_balance' => Money::of($line->runningBalanceMinor, $currencyEnum)->jsonSerialize(),
            ], $statement->lines),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function balancesFor(int $studentId): array
    {
        return Invoice::query()
            ->where('student_id', $studentId)
            ->where('status', '!=', 'voided')
            ->selectRaw('currency, sum(net_minor) as billed, sum(balance_minor) as outstanding')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row): array => [
                'billed' => Money::of((int) $row->getAttribute('billed'), Currency::from($row->currency))->jsonSerialize(),
                'outstanding' => Money::of((int) $row->getAttribute('outstanding'), Currency::from($row->currency))->jsonSerialize(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function invoice(Invoice $invoice, bool $mayViewBalance, bool $withLines = false): array
    {
        $currency = Currency::from($invoice->currency);
        $row = [
            'id' => $invoice->ulid,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'net' => Money::of($invoice->net_minor, $currency)->jsonSerialize(),
        ];

        if ($mayViewBalance) {
            $row['paid'] = Money::of($invoice->paid_minor, $currency)->jsonSerialize();
            $row['balance'] = Money::of($invoice->balance_minor, $currency)->jsonSerialize();
        }

        if ($withLines) {
            $row['lines'] = $invoice->lines->map(fn ($line): array => [
                'description' => $line->description,
                'net' => Money::of($line->net_minor, $currency)->jsonSerialize(),
            ])->all();
        }

        return $row;
    }
}
