<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\JournalLine;
use Modules\Reporting\Domain\DataObjects\GenerateManagementReportData;

/**
 * ACT-GenerateDepartmentalReport (Book H3 FIN-12 §3/BR-FIN-12-001). Income, expense and the net
 * result for each cost centre in a period, summed straight from `journal_lines` (never a cached
 * balance). Income-statement accounts only; lines that carry no cost centre are shown on their own
 * row so the departments always add up to the whole income statement. One currency per run.
 */
final class GenerateDepartmentalReportAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{rows: list<array{cost_centre_id: int|null, code: string, name: string, income_minor: int, expense_minor: int, net_minor: int}>, total_income_minor: int, total_expense_minor: int, total_net_minor: int}
     */
    public function execute(GenerateManagementReportData $data): array
    {
        $lines = JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('currency', $data->currency)
            ->whereHas('account.accountType', fn ($q) => $q->where('statement', 'income_statement'))
            ->with('account.accountType')
            ->whereDate('effective_at', '>=', $data->periodStart->toDateString())
            ->whereDate('effective_at', '<=', $data->periodEnd->toDateString())
            ->get();

        $centres = CostCentre::withoutGlobalScopes()->where('school_id', $data->schoolId)->get()->keyBy('id');
        $totals = [];

        foreach ($lines as $line) {
            $key = $line->cost_centre_id ?? 0;
            $totals[$key] ??= ['income' => 0, 'expense' => 0];
            $isIncome = $line->account->accountType->code === 'INCOME';
            $signed = ($line->direction === 'CR' ? 1 : -1) * $line->amount_minor;

            if ($isIncome) {
                $totals[$key]['income'] += $signed;
            } else {
                $totals[$key]['expense'] -= $signed;
            }
        }

        $rows = [];

        foreach ($totals as $key => $sums) {
            $centre = $centres->get($key);
            $rows[] = [
                'cost_centre_id' => $key === 0 ? null : $key,
                'code' => $centre !== null ? $centre->code : '—',
                'name' => $centre !== null ? $centre->name : 'No cost centre',
                'income_minor' => $sums['income'],
                'expense_minor' => $sums['expense'],
                'net_minor' => $sums['income'] - $sums['expense'],
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['code'], $b['code']));

        return [
            'rows' => $rows,
            'total_income_minor' => array_sum(array_column($rows, 'income_minor')),
            'total_expense_minor' => array_sum(array_column($rows, 'expense_minor')),
            'total_net_minor' => array_sum(array_column($rows, 'net_minor')),
        ];
    }
}
