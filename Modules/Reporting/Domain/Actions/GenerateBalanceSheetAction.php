<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalLine;
use Modules\Reporting\Domain\DataObjects\GenerateBalanceSheetData;

/**
 * ACT-GenerateBalanceSheet (Book H3 FIN-12 §3/BR-FIN-12-001/003/004). The position
 * on a date, straight from `journal_lines` (never a cached balance): assets,
 * liabilities and equity are each account's cumulative balance up to `asAt`, and
 * the result of every income-statement account to date is shown as "current
 * earnings" so the sheet balances without anyone closing the year by hand.
 * `asKnownOn` bounds by `posted_at`, as every other FIN-12 report does. One
 * currency per run.
 */
final class GenerateBalanceSheetAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{sections: array<string, array{lines: list<array{account_id: int, code: string, name: string, amount_minor: int}>, total_minor: int}>, current_earnings_minor: int, total_assets_minor: int, total_liabilities_and_equity_minor: int, is_balanced: bool}
     */
    public function execute(GenerateBalanceSheetData $data): array
    {
        $query = JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('currency', $data->currency)
            ->whereDate('effective_at', '<=', $data->asAt->toDateString());

        if ($data->asKnownOn !== null) {
            $query->whereHas('journal', fn ($q) => $q->where('posted_at', '<=', $data->asKnownOn));
        }

        $byAccount = $query->get()->groupBy('account_id');
        $accounts = Account::withoutGlobalScopes()->with('accountType')->whereIn('id', $byAccount->keys())->get()->keyBy('id');

        $sections = ['ASSET' => [], 'LIABILITY' => [], 'EQUITY' => []];
        $earnings = 0;

        foreach ($byAccount as $accountId => $rows) {
            $account = $accounts->get($accountId);

            if ($account === null) {
                continue;
            }

            $debits = (int) $rows->where('direction', 'DR')->sum('amount_minor');
            $credits = (int) $rows->where('direction', 'CR')->sum('amount_minor');

            if ($account->accountType->statement === 'income_statement') {
                $earnings += $credits - $debits;

                continue;
            }

            $signed = $account->accountType->normal_balance === 'DR' ? $debits - $credits : $credits - $debits;

            if ($signed === 0) {
                continue;
            }

            $sections[$account->accountType->code][] = [
                'account_id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'amount_minor' => $signed,
            ];
        }

        $result = [];

        foreach ($sections as $type => $lines) {
            usort($lines, fn (array $a, array $b): int => strcmp($a['code'], $b['code']));
            $result[$type] = ['lines' => $lines, 'total_minor' => $this->total($lines)];
        }

        $assets = $result['ASSET']['total_minor'];
        $liabilitiesAndEquity = $result['LIABILITY']['total_minor'] + $result['EQUITY']['total_minor'] + $earnings;

        return [
            'sections' => $result,
            'current_earnings_minor' => $earnings,
            'total_assets_minor' => $assets,
            'total_liabilities_and_equity_minor' => $liabilitiesAndEquity,
            'is_balanced' => $assets === $liabilitiesAndEquity,
        ];
    }

    /**
     * @param  list<array{account_id: int, code: string, name: string, amount_minor: int}>  $lines
     */
    private function total(array $lines): int
    {
        return (int) Collection::make($lines)->sum('amount_minor');
    }
}
