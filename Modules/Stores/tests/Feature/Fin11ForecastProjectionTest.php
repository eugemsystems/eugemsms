<?php

use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Invoice;
use Modules\Stores\Domain\Actions\ProjectCashFlowAction;
use Modules\Stores\Domain\Actions\ProjectFeeIncomeAction;
use Modules\Stores\Domain\DataObjects\ProjectForecastData;

/**
 * @return array{school: School, year: AcademicYear}
 */
function fin11pFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term1 = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 1]);
    $term2 = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 2]);

    foreach ([[$term1, 10000, 8000], [$term1, 10000, 8000], [$term2, 10000, 5000]] as [$term, $net, $paid]) {
        Invoice::factory()->for($school)->create([
            'academic_year_id' => $year->id, 'term_id' => $term->id, 'currency' => 'USD',
            'gross_minor' => $net, 'net_minor' => $net, 'paid_minor' => $paid, 'balance_minor' => $net - $paid, 'status' => 'issued',
        ]);
    }

    Invoice::factory()->for($school)->create([
        'academic_year_id' => $year->id, 'term_id' => $term1->id, 'currency' => 'USD',
        'gross_minor' => 99999, 'net_minor' => 99999, 'paid_minor' => 0, 'balance_minor' => 99999, 'status' => 'voided',
    ]);

    return compact('school', 'year');
}

it('projects fee income from the reference year: billing scales with growth and fee increase, collection follows history (BR-FIN-11-013)', function (): void {
    $f = fin11pFixture();

    $result = app(ProjectFeeIncomeAction::class)->execute(new ProjectForecastData(
        schoolId: $f['school']->id, referenceAcademicYearId: $f['year']->id, enrolmentGrowthPercent: 10, feeIncreasePercent: 5,
    ));

    [$term1, $term2] = $result['projections']['terms'];

    expect($term1['reference_billed_minor'])->toBe(20000)
        ->and($term1['historical_collection_rate_percent'])->toBe(80.0)
        ->and($term1['projected_billed_minor'])->toBe(23100)
        ->and($term1['projected_collected_minor'])->toBe(18480)
        ->and($term2['historical_collection_rate_percent'])->toBe(50.0)
        ->and($result['projections']['total_billed_minor'])->toBe(23100 + 11550);
});

it('lets the caller override the collection rate for a scenario', function (): void {
    $f = fin11pFixture();

    $result = app(ProjectFeeIncomeAction::class)->execute(new ProjectForecastData(
        schoolId: $f['school']->id, referenceAcademicYearId: $f['year']->id, collectionRatePercentOverride: 90,
    ));

    expect($result['projections']['terms'][1]['projected_collected_minor'])->toBe(9000)
        ->and($result['projections']['terms'][1]['historical_collection_rate_percent'])->toBe(50.0);
});

it('combines receipts with payroll into a cumulative cash position (BR-FIN-11-014)', function (): void {
    $f = fin11pFixture();

    $result = app(ProjectCashFlowAction::class)->execute(new ProjectForecastData(
        schoolId: $f['school']->id, referenceAcademicYearId: $f['year']->id,
    ));

    $first = $result['projections']['terms'][0];

    expect($first['receipts_minor'])->toBe(16000)
        ->and($first['net_minor'])->toBe($first['receipts_minor'] - $first['payroll_minor'] - $first['commitments_minor'])
        ->and($result['projections']['closing_position_minor'])->toBe(array_sum(array_column($result['projections']['terms'], 'net_minor')));
});
