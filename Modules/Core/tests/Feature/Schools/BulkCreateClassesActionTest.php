<?php

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Schools\BulkCreateClassesAction;
use Modules\Core\Domain\DataObjects\Schools\BulkClassData;
use Modules\Core\Domain\DataObjects\Schools\CreateClassData;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;

it('creates every class in the batch', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->create();

    $result = app(BulkCreateClassesAction::class)->execute(new BulkClassData([
        new CreateClassData($school->id, $year->id, $gradeLevel->id, 'F3A', 'Form 3 A'),
        new CreateClassData($school->id, $year->id, $gradeLevel->id, 'F3B', 'Form 3 B'),
        new CreateClassData($school->id, $year->id, $gradeLevel->id, 'F3C', 'Form 3 C'),
    ]));

    expect($result->createdCount)->toBe(3)
        ->and($result->createdClassIds)->toHaveCount(3);
});

it('rolls the whole batch back when one row fails validation', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->create();

    try {
        app(BulkCreateClassesAction::class)->execute(new BulkClassData([
            new CreateClassData($school->id, $year->id, $gradeLevel->id, 'F3A', 'Form 3 A'),
            new CreateClassData($school->id, $year->id, $gradeLevel->id, 'F3A', 'Duplicate code'),
        ]));
    } catch (ValidationException) {
        // expected
    }

    expect(SchoolClass::withoutGlobalScopes()->where('school_id', $school->id)->count())->toBe(0);
});
