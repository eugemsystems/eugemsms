<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalLine;
use Modules\Reporting\Domain\DataObjects\GenerateTrialBalanceData;
use Modules\Reporting\Domain\DataObjects\TrialBalanceResult;

/**
 * ACT-GenerateTrialBalance (Book H3 FIN-12 §3/BR-FIN-12-001/003/004/005
 * (AC-FIN-12-001)). Producible for any date, including a fully closed
 * and archived period — a pure query over `journal_lines`, so it
 * reproduces identically no matter when it's re-run. `asKnownOn`, when
 * given, additionally bounds by `posted_at` (BR-FIN-12-004); the
 * reconciling items are exactly the lines that fall on or before `asAt`
 * but were posted after `asKnownOn` — prior-period adjustments,
 * itemised on their own, never blended into the rows above them
 * (BR-FIN-12-005), mirroring `GenerateIncomeStatementAction`'s own
 * treatment of the identical rule for that statement.
 */
final class GenerateTrialBalanceAction extends Action
{
    public function execute(GenerateTrialBalanceData $data): TrialBalanceResult
    {
        $baseQuery = fn () => JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->whereDate('effective_at', '<=', $data->asAt->toDateString());

        $currentQuery = $baseQuery();

        if ($data->asKnownOn !== null) {
            $currentQuery->whereHas('journal', fn ($q) => $q->where('posted_at', '<=', $data->asKnownOn));
        }

        $rows = $this->summariseByAccountCurrency($currentQuery->get());
        $reconcilingItems = [];

        if ($data->asKnownOn !== null) {
            $afterKnownOn = $baseQuery()->whereHas('journal', fn ($q) => $q->where('posted_at', '>', $data->asKnownOn))->get();
            $reconcilingItems = $this->summariseByAccountCurrency($afterKnownOn);
        }

        return new TrialBalanceResult(rows: $rows, reconcilingItems: $reconcilingItems);
    }

    /**
     * @param  Collection<int, JournalLine>  $journalLines
     * @return array<int, array{account_id: int, code: string, name: string, currency: string, debit_minor: int, credit_minor: int}>
     */
    private function summariseByAccountCurrency(Collection $journalLines): array
    {
        $byAccountCurrency = $journalLines->groupBy(fn (JournalLine $line): string => "{$line->account_id}:{$line->currency}");

        if ($byAccountCurrency->isEmpty()) {
            return [];
        }

        $accountIds = $byAccountCurrency->keys()->map(fn (string $key): int => (int) explode(':', $key)[0])->unique();
        $accounts = Account::withoutGlobalScopes()->whereIn('id', $accountIds)->get()->keyBy('id');

        return $byAccountCurrency->map(function (Collection $rows, string $key) use ($accounts): array {
            [$accountId, $currency] = explode(':', $key);
            $account = $accounts->get((int) $accountId);

            return [
                'account_id' => (int) $accountId,
                'code' => $account !== null ? $account->code : '',
                'name' => $account !== null ? $account->name : '',
                'currency' => $currency,
                'debit_minor' => (int) $rows->where('direction', 'DR')->sum('amount_minor'),
                'credit_minor' => (int) $rows->where('direction', 'CR')->sum('amount_minor'),
            ];
        })->values()->all();
    }
}
