<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalLine;
use Modules\Reporting\Domain\DataObjects\GenerateIncomeStatementData;
use Modules\Reporting\Domain\DataObjects\IncomeStatementResult;

/**
 * ACT-GenerateIncomeStatement (Book H3 FIN-12 §3 ⭐/BR-FIN-12-001/003/
 * 004/005 (AC-FIN-12-001/002/003)). Every figure comes straight from
 * `journal_lines` — never a cached balance — filtered to accounts
 * whose `account_type.statement = 'income_statement'`. `effective_at`
 * always bounds which journal lines belong to the period;
 * `asKnownOn`, when given, additionally bounds by `posted_at`
 * (BR-FIN-12-004) — the two are genuinely separate columns on every
 * journal (Book B FIN-01's own doctrine), so this is a filter, not a
 * reconstruction. The reconciling items are exactly the lines that
 * fall inside the period but were posted after `asKnownOn` — prior-
 * period adjustments, itemised on their own, never blended into the
 * account lines above them (BR-FIN-12-005).
 */
final class GenerateIncomeStatementAction extends Action
{
    public function execute(GenerateIncomeStatementData $data): IncomeStatementResult
    {
        $baseQuery = fn () => JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->whereHas('account.accountType', fn ($q) => $q->where('statement', 'income_statement'))
            ->whereDate('effective_at', '>=', $data->periodStart->toDateString())
            ->whereDate('effective_at', '<=', $data->periodEnd->toDateString());

        $currentQuery = $baseQuery();

        if ($data->asKnownOn !== null) {
            $currentQuery->whereHas('journal', fn ($q) => $q->where('posted_at', '<=', $data->asKnownOn));
        }

        $lines = $this->summariseByAccount($currentQuery->get());
        $net = (int) array_sum(array_column($lines, 'amount_minor'));

        $reconcilingItems = [];
        $reconcilingTotal = 0;

        if ($data->asKnownOn !== null) {
            $afterKnownOn = $baseQuery()->whereHas('journal', fn ($q) => $q->where('posted_at', '>', $data->asKnownOn))->get();
            $reconcilingItems = $this->summariseByAccount($afterKnownOn);
            $reconcilingTotal = (int) array_sum(array_column($reconcilingItems, 'amount_minor'));
        }

        return new IncomeStatementResult(
            lines: $lines,
            netMinor: $net,
            reconcilingItems: $reconcilingItems,
            reconcilingTotalMinor: $reconcilingTotal,
        );
    }

    /**
     * @param  Collection<int, JournalLine>  $journalLines
     * @return array<int, array{account_id: int, code: string, name: string, amount_minor: int}>
     */
    private function summariseByAccount(Collection $journalLines): array
    {
        $byAccount = $journalLines->groupBy('account_id');

        if ($byAccount->isEmpty()) {
            return [];
        }

        $accounts = Account::withoutGlobalScopes()->whereIn('id', $byAccount->keys())->get()->keyBy('id');

        return $byAccount->map(function (Collection $rows, int $accountId) use ($accounts): array {
            $credits = (int) $rows->where('direction', 'CR')->sum('amount_minor');
            $debits = (int) $rows->where('direction', 'DR')->sum('amount_minor');
            $account = $accounts->get($accountId);

            return [
                'account_id' => $accountId,
                'code' => $account !== null ? $account->code : '',
                'name' => $account !== null ? $account->name : '',
                'amount_minor' => $credits - $debits,
            ];
        })->values()->all();
    }
}
