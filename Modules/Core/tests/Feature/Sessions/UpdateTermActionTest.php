<?php

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Sessions\CreateTermAction;
use Modules\Core\Domain\Actions\Sessions\UpdateTermAction;
use Modules\Core\Domain\DataObjects\Sessions\CreateTermData;
use Modules\Core\Domain\DataObjects\Sessions\UpdateTermData;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

it('updates a term\'s name and dates', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 1, 'Term 1', Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30')));

    $updated = (new UpdateTermAction)->execute(new UpdateTermData(
        termId: $term->id,
        schoolId: $school->id,
        academicYearId: $year->id,
        number: 1,
        name: 'Term One (Revised)',
        startsOn: Carbon::parse('2026-01-10'),
        endsOn: Carbon::parse('2026-04-20'),
    ));

    expect($updated->name)->toBe('Term One (Revised)')
        ->and($updated->starts_on->toDateString())->toBe('2026-01-10')
        ->and($updated->ends_on->toDateString())->toBe('2026-04-20');
});

it('allows saving a term without triggering its own overlap check', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 1, 'Term 1', Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30')));

    $updated = (new UpdateTermAction)->execute(new UpdateTermData(
        termId: $term->id,
        schoolId: $school->id,
        academicYearId: $year->id,
        number: 1,
        name: $term->name,
        startsOn: $term->starts_on,
        endsOn: $term->ends_on,
    ));

    expect($updated->id)->toBe($term->id);
});

it('rejects updating a term to overlap another term in the same year', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 1, 'Term 1', Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30')));
    $term2 = (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 2, 'Term 2', Carbon::parse('2026-05-01'), Carbon::parse('2026-08-31')));

    (new UpdateTermAction)->execute(new UpdateTermData(
        termId: $term2->id,
        schoolId: $school->id,
        academicYearId: $year->id,
        number: 2,
        name: $term2->name,
        startsOn: Carbon::parse('2026-04-15'),
        endsOn: Carbon::parse('2026-08-31'),
    ));
})->throws(ValidationException::class);

it('rejects updating a term number to collide with another term in the same year', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 1, 'Term 1', Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30')));
    $term2 = (new CreateTermAction)->execute(new CreateTermData($school->id, $year->id, 2, 'Term 2', Carbon::parse('2026-05-01'), Carbon::parse('2026-08-31')));

    (new UpdateTermAction)->execute(new UpdateTermData(
        termId: $term2->id,
        schoolId: $school->id,
        academicYearId: $year->id,
        number: 1,
        name: $term2->name,
        startsOn: $term2->starts_on,
        endsOn: $term2->ends_on,
    ));
})->throws(ValidationException::class);
