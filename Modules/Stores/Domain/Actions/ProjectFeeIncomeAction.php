<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Invoice;
use Modules\Stores\Domain\DataObjects\ProjectForecastData;

/**
 * ACT-ProjectFeeIncome (Book H1 FIN-11 §6/BR-FIN-11-013). Computes the fee-income
 * projection from what a reference year actually billed and collected, term by
 * term: billing scales with the enrolment-growth and fee-increase assumptions,
 * and collection follows the historical collection rate for that term unless the
 * caller overrides it. Read-only — the result is stored as a labelled scenario
 * by `CreateForecastAction`, never as the approved budget (BR-FIN-11-015).
 *
 * Drawn from FIN-03 invoice actuals rather than re-deriving each learner's fee
 * structure: voided invoices are excluded and the figures are in one currency.
 */
final class ProjectFeeIncomeAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{assumptions: array<string, mixed>, projections: array{currency: string, terms: list<array<string, mixed>>, total_billed_minor: int, total_collected_minor: int}}
     */
    public function execute(ProjectForecastData $data): array
    {
        $growth = 1 + ($data->enrolmentGrowthPercent / 100);
        $increase = 1 + ($data->feeIncreasePercent / 100);

        $terms = [];
        $totalBilled = 0;
        $totalCollected = 0;

        foreach (Term::query()->where('academic_year_id', $data->referenceAcademicYearId)->orderBy('number')->get() as $term) {
            $invoices = Invoice::query()
                ->where('term_id', $term->id)
                ->where('currency', $data->currency)
                ->where('status', '!=', 'voided');

            $billedMinor = (int) (clone $invoices)->sum('net_minor');
            $paidMinor = (int) (clone $invoices)->sum('paid_minor');
            $learners = (int) (clone $invoices)->distinct()->count('student_id');

            $historicalRate = $billedMinor > 0 ? min(100.0, round($paidMinor / $billedMinor * 100, 2)) : 0.0;
            $rate = $data->collectionRatePercentOverride ?? $historicalRate;

            $projectedBilled = (int) round($billedMinor * $growth * $increase);
            $projectedCollected = (int) round($projectedBilled * $rate / 100);

            $terms[] = [
                'term_number' => $term->number,
                'term_name' => $term->name,
                'reference_billed_minor' => $billedMinor,
                'reference_learners' => $learners,
                'projected_learners' => (int) round($learners * $growth),
                'historical_collection_rate_percent' => $historicalRate,
                'collection_rate_percent' => $rate,
                'projected_billed_minor' => $projectedBilled,
                'projected_collected_minor' => $projectedCollected,
            ];

            $totalBilled += $projectedBilled;
            $totalCollected += $projectedCollected;
        }

        return [
            'assumptions' => [
                'reference_academic_year_id' => $data->referenceAcademicYearId,
                'currency' => $data->currency,
                'enrolment_growth_percent' => $data->enrolmentGrowthPercent,
                'fee_increase_percent' => $data->feeIncreasePercent,
                'collection_rate_percent_override' => $data->collectionRatePercentOverride,
            ],
            'projections' => [
                'currency' => $data->currency,
                'terms' => $terms,
                'total_billed_minor' => $totalBilled,
                'total_collected_minor' => $totalCollected,
            ],
        ];
    }
}
