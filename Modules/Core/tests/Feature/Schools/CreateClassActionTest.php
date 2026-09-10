<?php

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Schools\CreateClassAction;
use Modules\Core\Domain\DataObjects\Schools\CreateClassData;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;

it('creates a class for a specific academic year (BR-CORE-02-004)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->create();

    $class = (new CreateClassAction)->execute(new CreateClassData(
        schoolId: $school->id,
        academicYearId: $year->id,
        gradeLevelId: $gradeLevel->id,
        code: 'F3B',
        name: 'Form 3 Blue',
        streamLabel: 'Blue',
    ));

    expect($class->exists)->toBeTrue()
        ->and($class->academic_year_id)->toBe($year->id)
        ->and($class->grade_level_id)->toBe($gradeLevel->id);
});

it('allows the same class code in two different academic years', function (): void {
    $school = School::factory()->create();
    $yearA = AcademicYear::factory()->for($school)->create();
    $yearB = AcademicYear::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->create();

    $classA = (new CreateClassAction)->execute(new CreateClassData($school->id, $yearA->id, $gradeLevel->id, 'F3B', 'Form 3 Blue'));
    $classB = (new CreateClassAction)->execute(new CreateClassData($school->id, $yearB->id, $gradeLevel->id, 'F3B', 'Form 3 Blue'));

    expect($classA->id)->not->toBe($classB->id);
});

it('rejects a duplicate class code within the same academic year', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->create();

    (new CreateClassAction)->execute(new CreateClassData($school->id, $year->id, $gradeLevel->id, 'DUP', 'A'));
    (new CreateClassAction)->execute(new CreateClassData($school->id, $year->id, $gradeLevel->id, 'DUP', 'B'));
})->throws(ValidationException::class);

it('refuses a grade level that does not belong to the school', function (): void {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $foreignGradeLevel = GradeLevel::factory()->for($otherSchool)->create();

    (new CreateClassAction)->execute(new CreateClassData($school->id, $year->id, $foreignGradeLevel->id, 'X', 'X'));
})->throws(ValidationException::class);
