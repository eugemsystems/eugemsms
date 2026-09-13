<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CalculateAccountTreeBalancesData;

/**
 * ACT-CalculateAccountTreeBalances (Book B FIN-01 §3/§8, backs
 * `Finance\Accounts\Tree`). One grouped aggregate query directly against
 * `journal_lines` (mirroring `RebuildAccountBalancesAction`'s own query
 * shape, minus the write) rather than either N+1-calling
 * `CalculateAccountBalanceAction` per account, or trusting
 * `account_balances` — the cache is keyed per term, and a tree-wide
 * "current balance" is an all-terms rollup the cache doesn't directly
 * answer. Still a from-source figure, just batched.
 *
 * Pulled out of the Livewire component itself (2026-09-13): Book A Part
 * 1.1's CI rule forbids `DB::table(` anywhere under a module's
 * `Livewire/` directory, no read/write exception — every query, even a
 * read-only one, belongs in the domain layer.
 */
final class CalculateAccountTreeBalancesAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array<int, array<string, int>> account_id => currency => signed minor balance
     */
    public function execute(CalculateAccountTreeBalancesData $data): array
    {
        $rows = DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->where('journal_lines.school_id', $data->schoolId)
            ->selectRaw('journal_lines.account_id, journal_lines.currency, account_types.normal_balance, '
                ."SUM(CASE WHEN journal_lines.direction = 'DR' THEN journal_lines.amount_minor ELSE 0 END) as debit_minor, "
                ."SUM(CASE WHEN journal_lines.direction = 'CR' THEN journal_lines.amount_minor ELSE 0 END) as credit_minor")
            ->groupBy('journal_lines.account_id', 'journal_lines.currency', 'account_types.normal_balance')
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            $balance = $row->normal_balance === 'DR'
                ? $row->debit_minor - $row->credit_minor
                : $row->credit_minor - $row->debit_minor;

            $balances[$row->account_id][$row->currency] = (int) $balance;
        }

        return $balances;
    }
}
