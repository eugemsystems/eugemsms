<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\Payroll\Models\PayrollRun;
use Modules\Stores\Domain\DataObjects\ProjectForecastData;
use Modules\Stores\Models\BudgetCommitment;

/**
 * ACT-ProjectCashFlow (Book H1 FIN-11 §6/BR-FIN-11-014). Per term: projected fee
 * receipts (`ProjectFeeIncomeAction`), less the school's open commitments
 * (outstanding purchase orders, charged to the first term) and payroll (the most
 * recent finalised run's gross plus employer cost, once per month of the term).
 * Statutory obligations ride inside the payroll figure (PAYE, NSSA and levies are
 * lines of the same run). A scenario, not a plan (BR-FIN-11-015).
 */
final class ProjectCashFlowAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly ProjectFeeIncomeAction $projectFeeIncome,
    ) {}

    /**
     * @return array{assumptions: array<string, mixed>, projections: array{currency: string, terms: list<array<string, mixed>>, closing_position_minor: int}}
     */
    public function execute(ProjectForecastData $data): array
    {
        $fees = $this->projectFeeIncome->execute($data);

        $monthlyPayrollMinor = $this->monthlyPayrollMinor($data->currency);
        $openCommitmentsMinor = (int) BudgetCommitment::query()->where('status', 'open')->where('currency', $data->currency)->sum('outstanding_minor');
        $months = Term::query()->where('academic_year_id', $data->referenceAcademicYearId)->orderBy('number')->get()
            ->mapWithKeys(fn (Term $term): array => [$term->number => max(1, (int) round($term->starts_on->diffInMonths($term->ends_on)))]);

        $position = 0;
        $terms = [];

        foreach ($fees['projections']['terms'] as $index => $term) {
            $payroll = $monthlyPayrollMinor * ($months[$term['term_number']] ?? 3);
            $commitments = $index === 0 ? $openCommitmentsMinor : 0;
            $net = $term['projected_collected_minor'] - $payroll - $commitments;
            $position += $net;

            $terms[] = [
                'term_number' => $term['term_number'],
                'term_name' => $term['term_name'],
                'receipts_minor' => $term['projected_collected_minor'],
                'payroll_minor' => $payroll,
                'commitments_minor' => $commitments,
                'net_minor' => $net,
                'cumulative_minor' => $position,
            ];
        }

        return [
            'assumptions' => $fees['assumptions'] + ['monthly_payroll_minor' => $monthlyPayrollMinor, 'open_commitments_minor' => $openCommitmentsMinor],
            'projections' => ['currency' => $data->currency, 'terms' => $terms, 'closing_position_minor' => $position],
        ];
    }

    private function monthlyPayrollMinor(string $currency): int
    {
        $run = PayrollRun::query()->whereIn('status', ['approved', 'posted'])->orderByDesc('period_start')->first();

        return $run === null ? 0 : $run->gross_minor + $run->employer_cost_minor;
    }
}
