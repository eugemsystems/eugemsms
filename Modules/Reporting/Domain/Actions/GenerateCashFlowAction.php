<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\JournalLine;
use Modules\Reporting\Domain\DataObjects\GenerateCashFlowData;

/**
 * ACT-GenerateCashFlow (Book H3 FIN-12 §3/BR-FIN-12-001/003). A direct-method
 * cash-flow statement: every journal line that touched a bank account's ledger
 * account in the period, grouped by the type of journal that moved the money,
 * between a computed opening and closing cash position. Closing always equals
 * opening plus net movement, because both are sums of the same journal lines.
 * Cash held in tills is not a bank account and is out of scope until it is
 * banked. One currency per run.
 */
final class GenerateCashFlowAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{opening_minor: int, inflows: list<array{journal_type: string, amount_minor: int}>, outflows: list<array{journal_type: string, amount_minor: int}>, total_inflows_minor: int, total_outflows_minor: int, net_minor: int, closing_minor: int}
     */
    public function execute(GenerateCashFlowData $data): array
    {
        $cashAccountIds = BankAccount::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->pluck('gl_account_id')
            ->all();

        $base = fn () => JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('currency', $data->currency)
            ->whereIn('account_id', $cashAccountIds);

        $opening = $this->net($base()->whereDate('effective_at', '<', $data->periodStart->toDateString())->get());

        $inflows = [];
        $outflows = [];

        $lines = $base()
            ->whereDate('effective_at', '>=', $data->periodStart->toDateString())
            ->whereDate('effective_at', '<=', $data->periodEnd->toDateString())
            ->with('journal:id,journal_type')
            ->get();

        foreach ($lines as $line) {
            $type = $line->journal !== null ? $line->journal->journal_type : 'unknown';

            if ($line->direction === 'DR') {
                $inflows[$type] = ($inflows[$type] ?? 0) + $line->amount_minor;
            } else {
                $outflows[$type] = ($outflows[$type] ?? 0) + $line->amount_minor;
            }
        }

        ksort($inflows);
        ksort($outflows);

        $totalIn = array_sum($inflows);
        $totalOut = array_sum($outflows);

        return [
            'opening_minor' => $opening,
            'inflows' => $this->rows($inflows),
            'outflows' => $this->rows($outflows),
            'total_inflows_minor' => $totalIn,
            'total_outflows_minor' => $totalOut,
            'net_minor' => $totalIn - $totalOut,
            'closing_minor' => $opening + $totalIn - $totalOut,
        ];
    }

    /**
     * @param  iterable<JournalLine>  $lines
     */
    private function net(iterable $lines): int
    {
        $net = 0;

        foreach ($lines as $line) {
            $net += $line->direction === 'DR' ? $line->amount_minor : -$line->amount_minor;
        }

        return $net;
    }

    /**
     * @param  array<string, int>  $totals
     * @return list<array{journal_type: string, amount_minor: int}>
     */
    private function rows(array $totals): array
    {
        $rows = [];

        foreach ($totals as $type => $amount) {
            $rows[] = ['journal_type' => $type, 'amount_minor' => $amount];
        }

        return $rows;
    }
}
