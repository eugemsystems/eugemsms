<?php

use Modules\Core\Domain\Actions\Sessions\DeleteAcademicYearAction;
use Modules\Core\Domain\DataObjects\Sessions\DeleteAcademicYearData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

it('deletes a planned academic year with no terms', function (): void {
    $school = School::factory()->create();
    // Factories default academic_state/financial_state to 'open' for
    // test convenience — real years created via ACT-CreateAcademicYear
    // start 'planned', which is what this action requires to be safe to
    // delete, so it's set explicitly here.
    $year = AcademicYear::factory()->for($school)->create(['academic_state' => 'planned', 'financial_state' => 'planned']);

    (new DeleteAcademicYearAction)->execute(new DeleteAcademicYearData(
        yearId: $year->id,
        schoolId: $school->id,
    ));

    expect(AcademicYear::withoutGlobalScopes()->find($year->id))->toBeNull();
});

it('deletes a planned academic year and cascades its still-planned terms', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create(['academic_state' => 'planned', 'financial_state' => 'planned']);
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['academic_state' => 'planned', 'financial_state' => 'planned']);

    (new DeleteAcademicYearAction)->execute(new DeleteAcademicYearData(
        yearId: $year->id,
        schoolId: $school->id,
    ));

    expect(AcademicYear::withoutGlobalScopes()->find($year->id))->toBeNull()
        ->and(Term::withoutGlobalScopes()->find($term->id))->toBeNull();
});

it('refuses to delete the current academic year', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->current()->create();

    (new DeleteAcademicYearAction)->execute(new DeleteAcademicYearData(
        yearId: $year->id,
        schoolId: $school->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('refuses to delete a year that has moved past planned state', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create(['academic_state' => 'open']);

    (new DeleteAcademicYearAction)->execute(new DeleteAcademicYearData(
        yearId: $year->id,
        schoolId: $school->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('refuses to delete a year with a term that has moved past planned state', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create(['academic_state' => 'planned', 'financial_state' => 'planned']);
    Term::factory()->for($school)->for($year, 'academicYear')->create(['academic_state' => 'open']);

    (new DeleteAcademicYearAction)->execute(new DeleteAcademicYearData(
        yearId: $year->id,
        schoolId: $school->id,
    ));
})->throws(InvalidStateTransitionException::class);
