<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Bank;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ConvertBankLineToSuspenseAction;
use Modules\Finance\Domain\Actions\MatchBankStatementLineAction;
use Modules\Finance\Domain\DataObjects\ConvertBankLineToSuspenseData;
use Modules\Finance\Domain\DataObjects\MatchBankStatementLineData;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\Finance\Models\BankStatement;
use Modules\Finance\Models\BankStatementLine;
use Modules\Finance\Models\Receipt;

/**
 * `Finance\Bank\Matching` (Book B FIN-05 §8/BR-FIN-05-011/012,
 * `finance.bank.reconcile`). Candidate receipts are suggested by exact
 * amount/currency within a date window — `ImportBankStatementAction`
 * only auto-matches on an exact reference (see its own docblock);
 * everything else, including the "confidence score" shown here per
 * candidate, is this screen's own suggestion for a human to confirm
 * or reject with `MatchBankStatementLineAction`, never applied by
 * itself.
 */
#[Title('Bank statement matching')]
#[Layout('layouts.app')]
final class Matching extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use ResolvesSystemAccounts;
    use Toasts;

    public BankStatement $statement;

    public ?int $matchingLineId = null;

    public function mount(School $school, BankStatement $statement): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('finance.bank.reconcile');

        abort_unless($statement->school_id === $school->id, 404);

        $this->statement = $statement;
    }

    public function startMatching(int $lineId): void
    {
        $this->matchingLineId = $lineId;
    }

    public function cancelMatching(): void
    {
        $this->matchingLineId = null;
    }

    public function confirmMatch(int $receiptId, int $confidence): void
    {
        $line = BankStatementLine::where('statement_id', $this->statement->id)->findOrFail($this->matchingLineId);

        app(MatchBankStatementLineAction::class)->execute(new MatchBankStatementLineData(
            bankStatementLineId: $line->id,
            matchedType: 'receipt',
            matchedId: $receiptId,
            matchConfidence: $confidence,
            matchedByUserId: (int) Auth::id(),
        ));

        $this->matchingLineId = null;
        $this->statement = $this->statement->fresh();
        $this->toast(__('Line matched.'));
    }

    public function convertToSuspense(int $lineId): void
    {
        $line = BankStatementLine::where('statement_id', $this->statement->id)->findOrFail($lineId);
        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No active academic year/term is set for this school.'), 'danger');

            return;
        }

        try {
            app(ConvertBankLineToSuspenseAction::class)->execute(new ConvertBankLineToSuspenseData(
                bankStatementLineId: $line->id,
                academicYearId: $yearId,
                termId: $termId,
                convertedByUserId: (int) Auth::id(),
                suspenseAccountId: $this->requireSystemAccount('suspense', __('Suspense')),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->statement = $this->statement->fresh();
        $this->toast(__('Converted to a suspense item.'));
    }

    /**
     * @return Collection<int, Receipt>
     */
    public function candidatesFor(BankStatementLine $line): Collection
    {
        if (! $line->isCredit()) {
            return new Collection;
        }

        $alreadyMatchedReceiptIds = BankStatementLine::where('school_id', $this->school->id)
            ->where('matched_type', 'receipt')
            ->whereNotNull('matched_id')
            ->pluck('matched_id');

        // Sorted in PHP, not SQL — `DATEDIFF` is MySQL-only syntax and
        // this candidate set is already narrowed to a handful of rows
        // by the filters above, so a portable in-memory sort costs
        // nothing and works identically on SQLite (tests) and MySQL.
        return Receipt::query()
            ->where('school_id', $this->school->id)
            ->where('currency', $line->currency)
            ->where('amount_minor', $line->credit_minor)
            ->where('status', '!=', 'voided')
            ->whereNotIn('id', $alreadyMatchedReceiptIds)
            ->whereBetween('effective_date', [
                $line->transaction_date->copy()->subDays(5),
                $line->transaction_date->copy()->addDays(5),
            ])
            ->get()
            ->sortBy(fn (Receipt $receipt): int => (int) $line->transaction_date->diffInDays($receipt->effective_date, absolute: true))
            ->take(5)
            ->values();
    }

    /**
     * A same-day, same-amount, same-currency candidate is a very
     * strong suggestion; confidence falls off with each day of
     * distance from the statement line's own transaction date. This
     * is purely a display ranking — the figure recorded against the
     * match is whatever this same number is at the moment a human
     * confirms it (`MatchBankStatementLineAction`'s own
     * `matchConfidence` parameter), never applied automatically.
     */
    public function suggestedConfidence(BankStatementLine $line, Receipt $receipt): int
    {
        $daysApart = (int) $line->transaction_date->diffInDays($receipt->effective_date, absolute: true);

        return max(60, 100 - ($daysApart * 8));
    }

    public function render(): View
    {
        $lines = BankStatementLine::where('statement_id', $this->statement->id)->orderBy('line_number')->get();

        return view('finance::bank.matching', [
            'lines' => $lines,
        ]);
    }
}
