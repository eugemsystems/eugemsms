<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\JournalLine;
use Modules\Reporting\Domain\DataObjects\GenerateBalanceSheetData;
use Modules\Reporting\Domain\DataObjects\GenerateKeyFinancialRatiosData;
use Modules\Reporting\Domain\DataObjects\GenerateManagementReportData;

/**
 * ACT-GenerateKeyFinancialRatios (Book H3 FIN-12 §6/BR-FIN-12-015 —
 * "Board reporting packs assemble the standard statements plus
 * enrolment, collection rate and key ratios in one document"). The
 * spec names no specific ratio list — a deliberate, user-approved
 * judgment call, not a guess (2026-10-08): four conventional,
 * well-defined ratios computable straight from `journal_lines`, never
 * a cached balance, same doctrine every other FIN-12 report follows.
 *
 * **"Current ratio" is not literally buildable** — this codebase's
 * chart of accounts has no current/non-current classification
 * anywhere (`Account`/`AccountType` carry no such column), so there is
 * no way to separate current from non-current assets/liabilities.
 * Rather than mislabel a different figure as "current ratio", this
 * reports **total-assets-to-total-liabilities** instead, named
 * exactly that — a real solvency indicator from the same Balance
 * Sheet data, just not the textbook current ratio.
 *
 * Staff cost is summed from `journal_lines` whose own journal carries
 * `journal_type = 'PAYROLL'` (`PostPayrollRunAction`'s own tag) —
 * `PayrollGlAccounts` is passed ad hoc by each posting call with no
 * stored school-level account mapping to look up (see its own
 * docblock), so filtering by the journal's type is the only
 * GL-only way to isolate payroll's own expense lines without
 * inventing a configuration that does not exist.
 */
final class GenerateKeyFinancialRatiosAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly GenerateBalanceSheetAction $generateBalanceSheet,
        private readonly GenerateCollectionReportAction $generateCollectionReport,
    ) {}

    /**
     * @return array{
     *     operating_margin_percent: float|null,
     *     staff_cost_percent: float|null,
     *     asset_to_liability_ratio: float|null,
     *     collection_rate_percent: float|null,
     *     total_income_minor: int,
     *     total_expense_minor: int,
     *     staff_cost_minor: int,
     *     total_assets_minor: int,
     *     total_liabilities_minor: int,
     * }
     */
    public function execute(GenerateKeyFinancialRatiosData $data): array
    {
        $totalIncomeMinor = $this->statementTotal($data, 'INCOME');
        $totalExpenseMinor = $this->statementTotal($data, 'EXPENSE');
        $staffCostMinor = $this->staffCostMinor($data);

        $balanceSheet = $this->generateBalanceSheet->execute(new GenerateBalanceSheetData(
            schoolId: $data->schoolId, asAt: $data->periodEnd, currency: $data->currency,
        ));

        $collection = $this->generateCollectionReport->execute(new GenerateManagementReportData(
            schoolId: $data->schoolId, periodStart: $data->periodStart, periodEnd: $data->periodEnd, currency: $data->currency,
        ));

        $totalLiabilitiesMinor = (int) $balanceSheet['sections']['LIABILITY']['total_minor'];

        return [
            'operating_margin_percent' => $totalIncomeMinor > 0 ? round(($totalIncomeMinor - $totalExpenseMinor) / $totalIncomeMinor * 100, 1) : null,
            'staff_cost_percent' => $totalIncomeMinor > 0 ? round($staffCostMinor / $totalIncomeMinor * 100, 1) : null,
            'asset_to_liability_ratio' => $totalLiabilitiesMinor > 0 ? round($balanceSheet['total_assets_minor'] / $totalLiabilitiesMinor, 2) : null,
            'collection_rate_percent' => $collection['rate_percent'],
            'total_income_minor' => $totalIncomeMinor,
            'total_expense_minor' => $totalExpenseMinor,
            'staff_cost_minor' => $staffCostMinor,
            'total_assets_minor' => (int) $balanceSheet['total_assets_minor'],
            'total_liabilities_minor' => $totalLiabilitiesMinor,
        ];
    }

    private function statementTotal(GenerateKeyFinancialRatiosData $data, string $accountTypeCode): int
    {
        $lines = JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('currency', $data->currency)
            ->whereHas('account.accountType', fn ($q) => $q->where('code', $accountTypeCode))
            ->whereDate('effective_at', '>=', $data->periodStart->toDateString())
            ->whereDate('effective_at', '<=', $data->periodEnd->toDateString())
            ->get();

        $credits = (int) $lines->where('direction', 'CR')->sum('amount_minor');
        $debits = (int) $lines->where('direction', 'DR')->sum('amount_minor');

        return $accountTypeCode === 'INCOME' ? $credits - $debits : $debits - $credits;
    }

    private function staffCostMinor(GenerateKeyFinancialRatiosData $data): int
    {
        $lines = JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('currency', $data->currency)
            ->where('direction', 'DR')
            ->whereHas('account.accountType', fn ($q) => $q->where('code', 'EXPENSE'))
            ->whereHas('journal', fn ($q) => $q->where('journal_type', 'PAYROLL'))
            ->whereDate('effective_at', '>=', $data->periodStart->toDateString())
            ->whereDate('effective_at', '<=', $data->periodEnd->toDateString())
            ->get();

        return (int) $lines->sum('amount_minor');
    }
}
