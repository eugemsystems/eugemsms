<?php

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Sessions\UpdateAcademicYearAction;
use Modules\Core\Domain\DataObjects\Sessions\UpdateAcademicYearData;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

it('updates an academic year\'s name and dates', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create(['name' => '2026']);

    $updated = (new UpdateAcademicYearAction)->execute(new UpdateAcademicYearData(
        yearId: $year->id,
        schoolId: $school->id,
        name: '2026 (Revised)',
        startsOn: Carbon::parse('2026-01-05'),
        endsOn: Carbon::parse('2026-12-20'),
        isCurrent: false,
    ));

    expect($updated->name)->toBe('2026 (Revised)')
        ->and($updated->starts_on->toDateString())->toBe('2026-01-05')
        ->and($updated->ends_on->toDateString())->toBe('2026-12-20');
});

it('rejects an end date before the start date', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();

    (new UpdateAcademicYearAction)->execute(new UpdateAcademicYearData(
        yearId: $year->id,
        schoolId: $school->id,
        name: $year->name,
        startsOn: Carbon::parse('2026-12-31'),
        endsOn: Carbon::parse('2026-01-01'),
        isCurrent: false,
    ));
})->throws(ValidationException::class);

it('rejects a duplicate name within the same school', function (): void {
    $school = School::factory()->create();
    AcademicYear::factory()->for($school)->create(['name' => '2025']);
    $year = AcademicYear::factory()->for($school)->create(['name' => '2026']);

    (new UpdateAcademicYearAction)->execute(new UpdateAcademicYearData(
        yearId: $year->id,
        schoolId: $school->id,
        name: '2025',
        startsOn: $year->starts_on,
        endsOn: $year->ends_on,
        isCurrent: false,
    ));
})->throws(ValidationException::class);

it('sets a year as current and clears the previously current year (BR-CORE-03-001)', function (): void {
    $school = School::factory()->create();
    $oldCurrent = AcademicYear::factory()->for($school)->current()->create();
    $year = AcademicYear::factory()->for($school)->create(['is_current' => false]);

    $updated = (new UpdateAcademicYearAction)->execute(new UpdateAcademicYearData(
        yearId: $year->id,
        schoolId: $school->id,
        name: $year->name,
        startsOn: $year->starts_on,
        endsOn: $year->ends_on,
        isCurrent: true,
    ));

    expect($updated->is_current)->toBeTrue()
        ->and($oldCurrent->fresh()->is_current)->toBeFalse();
});

it('leaves other years alone when a year is updated without changing is_current', function (): void {
    $school = School::factory()->create();
    $current = AcademicYear::factory()->for($school)->current()->create();
    $year = AcademicYear::factory()->for($school)->create(['is_current' => false]);

    (new UpdateAcademicYearAction)->execute(new UpdateAcademicYearData(
        yearId: $year->id,
        schoolId: $school->id,
        name: 'Renamed',
        startsOn: $year->starts_on,
        endsOn: $year->ends_on,
        isCurrent: false,
    ));

    expect($current->fresh()->is_current)->toBeTrue();
});
