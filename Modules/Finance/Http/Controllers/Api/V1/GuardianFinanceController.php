<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Finance\Models\Invoice;
use Modules\People\Domain\Support\LinkedLearners;
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
