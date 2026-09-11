<?php

use Modules\Core\Domain\Actions\Sessions\DeleteTermAction;
use Modules\Core\Domain\DataObjects\Sessions\DeleteTermData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

it('deletes a planned term', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    // Factories default academic_state/financial_state to 'open' for test
    // convenience — a real term created via ACT-CreateTerm starts
    // 'planned', which is what this action requires to be safe to delete.
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['academic_state' => 'planned', 'financial_state' => 'planned']);

    (new DeleteTermAction)->execute(new DeleteTermData(
        termId: $term->id,
        schoolId: $school->id,
        academicYearId: $year->id,
    ));

    expect(Term::withoutGlobalScopes()->find($term->id))->toBeNull();
});

it('refuses to delete the current term', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);

    (new DeleteTermAction)->execute(new DeleteTermData(
        termId: $term->id,
        schoolId: $school->id,
        academicYearId: $year->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('refuses to delete a term that has moved past planned state', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open']);

    (new DeleteTermAction)->execute(new DeleteTermData(
        termId: $term->id,
        schoolId: $school->id,
        academicYearId: $year->id,
    ));
})->throws(InvalidStateTransitionException::class);
