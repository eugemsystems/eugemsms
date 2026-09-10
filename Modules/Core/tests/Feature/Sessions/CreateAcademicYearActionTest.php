<?php

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Sessions\CreateAcademicYearAction;
use Modules\Core\Domain\DataObjects\Sessions\CreateYearData;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

it('creates an academic year in planned state', function (): void {
    $school = School::factory()->create();

    $year = app(CreateAcademicYearAction::class)->execute(new CreateYearData(
        schoolId: $school->id,
        name: '2026',
        startsOn: Carbon::parse('2026-01-01'),
        endsOn: Carbon::parse('2026-12-31'),
    ));

    expect($year->exists)->toBeTrue()
        ->and($year->academic_state->value)->toBe('planned')
        ->and($year->financial_state->value)->toBe('planned')
        ->and($year->is_current)->toBeFalse();
});

it('rejects a duplicate year name for the same school', function (): void {
    $school = School::factory()->create();

    app(CreateAcademicYearAction::class)->execute(new CreateYearData($school->id, '2026', Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')));
    app(CreateAcademicYearAction::class)->execute(new CreateYearData($school->id, '2026', Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')));
})->throws(ValidationException::class);

it('rejects an end date before the start date', function (): void {
    $school = School::factory()->create();

    app(CreateAcademicYearAction::class)->execute(new CreateYearData(
        $school->id, '2026', Carbon::parse('2026-12-31'), Carbon::parse('2026-01-01'),
    ));
})->throws(ValidationException::class);

it('auto-generates three non-overlapping terms covering the full year (BR-CORE-03-003)', function (): void {
    $school = School::factory()->create();

    $year = app(CreateAcademicYearAction::class)->execute(new CreateYearData(
        schoolId: $school->id,
        name: '2026',
        startsOn: Carbon::parse('2026-01-01'),
        endsOn: Carbon::parse('2026-12-31'),
        generateThreeTerms: true,
    ));

    $terms = Term::withoutGlobalScopes()->where('academic_year_id', $year->id)->orderBy('number')->get();

    expect($terms)->toHaveCount(3)
        ->and($terms->first()->starts_on->toDateString())->toBe('2026-01-01')
        ->and($terms->last()->ends_on->toDateString())->toBe('2026-12-31');

    // No gaps, no overlaps: each term starts the day after the previous ends.
    expect($terms[1]->starts_on->toDateString())->toBe($terms[0]->ends_on->copy()->addDay()->toDateString())
        ->and($terms[2]->starts_on->toDateString())->toBe($terms[1]->ends_on->copy()->addDay()->toDateString());
});
