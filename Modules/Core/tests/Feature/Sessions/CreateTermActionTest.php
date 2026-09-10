<?php

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Sessions\CreateTermAction;
use Modules\Core\Domain\DataObjects\Sessions\CreateTermData;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

it('creates a term', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();

    $term = (new CreateTermAction)->execute(new CreateTermData(
        schoolId: $school->id,
        academicYearId: $year->id,
        number: 1,
        name: 'Term 1',
        startsOn: Carbon::parse('2026-01-01'),
        endsOn: Carbon::parse('2026-04-30'),
    ));

    expect($term->exists)->toBeTrue()->and($term->academic_state->value)->toBe('planned');
});

it('rejects a term that overlaps another in the same year (BR-CORE-03-002)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();

    (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 1, 'Term 1', Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30')));
    (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 2, 'Term 2', Carbon::parse('2026-04-15'), Carbon::parse('2026-08-31')));
})->throws(ValidationException::class);

it('allows adjacent, non-overlapping terms', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();

    (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 1, 'Term 1', Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30')));
    $term2 = (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 2, 'Term 2', Carbon::parse('2026-05-01'), Carbon::parse('2026-08-31')));

    expect($term2->exists)->toBeTrue();
});

it('rejects a duplicate term number within the same year', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();

    (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 1, 'Term 1', Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30')));
    (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 1, 'Term 1 Retry', Carbon::parse('2026-05-01'), Carbon::parse('2026-08-31')));
})->throws(ValidationException::class);
