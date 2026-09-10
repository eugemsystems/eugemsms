<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\RebuildAccountBalancesData;
use Modules\Finance\Models\AccountBalance;

/**
 * ACT-RebuildAccountBalances (Book B FIN-01 §3/§5). The *only* writer
 * of `account_balances` — BR-FIN-01-024. Always recomputes fully from
 * `journal_lines`; incremental-from-watermark (the `last_line_id`
 * column exists for this) is a performance optimisation deferred past
 * this engine pass, the same way `PostJournalAction` doesn't yet update
 * the cache incrementally on every post — both land together once
 * there's a real workload to profile against.
 */
final class RebuildAccountBalancesAction extends Action
{
    public function execute(RebuildAccountBalancesData $data): int
    {
        $query = DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->where('journal_lines.school_id', $data->schoolId);

        if ($data->termId !== null) {
            $query->where('journal_lines.term_id', $data->termId);
        }

        $rows = $query
            ->selectRaw('journal_lines.account_id, journal_lines.term_id, journal_lines.currency, account_types.normal_balance, '
                ."SUM(CASE WHEN journal_lines.direction = 'DR' THEN journal_lines.amount_minor ELSE 0 END) as debit_minor, "
                ."SUM(CASE WHEN journal_lines.direction = 'CR' THEN journal_lines.amount_minor ELSE 0 END) as credit_minor, "
                .'COUNT(*) as line_count, MAX(journal_lines.id) as last_line_id')
            ->groupBy('journal_lines.account_id', 'journal_lines.term_id', 'journal_lines.currency', 'account_types.normal_balance')
            ->get();

        return $this->transaction(function () use ($rows, $data): int {
            $count = 0;

            foreach ($rows as $row) {
                $closing = $row->normal_balance === 'DR'
                    ? $row->debit_minor - $row->credit_minor
                    : $row->credit_minor - $row->debit_minor;

                AccountBalance::updateOrCreate(
                    [
                        'school_id' => $data->schoolId,
                        'account_id' => $row->account_id,
                        'term_id' => $row->term_id,
                        'currency' => $row->currency,
                    ],
                    [
                        'debit_minor' => $row->debit_minor,
                        'credit_minor' => $row->credit_minor,
                        'closing_minor' => $closing,
                        'line_count' => $row->line_count,
                        'last_line_id' => $row->last_line_id,
                        'rebuilt_at' => now(),
                    ],
                );

                $count++;
            }

            return $count;
        });
    }
}
