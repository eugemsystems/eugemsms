<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\FindStaleAccountBalancesData;
use Modules\Finance\Models\AccountBalance;

/**
 * ACT-FindStaleAccountBalances (Book B FIN-01 §3/§8, backs
 * `Finance\Integrity\Balances`). Compares the `account_balances` cache
 * against the same from-source aggregate `RebuildAccountBalancesAction`
 * itself computes — read-only, nothing is written here — surfacing any
 * (account, term, currency) triple where the cache disagrees with
 * source. BR-FIN-01-024/§3: the cache is verified against source, never
 * the other way around.
 *
 * Pulled out of the Livewire component itself (2026-09-13): Book A Part
 * 1.1's CI rule forbids `DB::table(` anywhere under a module's
 * `Livewire/` directory, no read/write exception — every query, even a
 * read-only one, belongs in the domain layer.
 */
final class FindStaleAccountBalancesAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array<int, array{account_id: int, term_id: int, currency: string, cached_closing_minor: int, source_closing_minor: int}>
     */
    public function execute(FindStaleAccountBalancesData $data): array
    {
        $sourceRows = DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->where('journal_lines.school_id', $data->schoolId)
            ->selectRaw('journal_lines.account_id, journal_lines.term_id, journal_lines.currency, account_types.normal_balance, '
                ."SUM(CASE WHEN journal_lines.direction = 'DR' THEN journal_lines.amount_minor ELSE 0 END) as debit_minor, "
                ."SUM(CASE WHEN journal_lines.direction = 'CR' THEN journal_lines.amount_minor ELSE 0 END) as credit_minor")
            ->groupBy('journal_lines.account_id', 'journal_lines.term_id', 'journal_lines.currency', 'account_types.normal_balance')
            ->get()
            ->keyBy(fn ($row): string => "{$row->account_id}:{$row->term_id}:{$row->currency}");

        $cachedRows = AccountBalance::query()
            ->where('school_id', $data->schoolId)
            ->get()
            ->keyBy(fn (AccountBalance $row): string => "{$row->account_id}:{$row->term_id}:{$row->currency}");

        $mismatches = [];

        foreach ($sourceRows as $key => $source) {
            $sourceClosing = $source->normal_balance === 'DR'
                ? $source->debit_minor - $source->credit_minor
                : $source->credit_minor - $source->debit_minor;

            $cached = $cachedRows->get($key);
            $cachedClosing = $cached === null ? 0 : $cached->closing_minor;

            if ($cachedClosing !== (int) $sourceClosing) {
                $mismatches[] = [
                    'account_id' => (int) $source->account_id,
                    'term_id' => (int) $source->term_id,
                    'currency' => (string) $source->currency,
                    'cached_closing_minor' => $cachedClosing,
                    'source_closing_minor' => (int) $sourceClosing,
                ];
            }
        }

        return $mismatches;
    }
}
