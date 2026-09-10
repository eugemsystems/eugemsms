<?php

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Schools\CreateGradeLevelAction;
use Modules\Core\Domain\DataObjects\Schools\CreateGradeLevelData;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;

it('creates a grade level under an existing section (BR-CORE-02-002)', function (): void {
    $school = School::factory()->create();
    $section = SchoolSection::factory()->for($school)->create();

    $gradeLevel = (new CreateGradeLevelAction)->execute(new CreateGradeLevelData(
        schoolId: $school->id,
        sectionId: $section->id,
        code: 'G3',
        name: 'Grade 3',
        ordinal: 3,
    ));

    expect($gradeLevel->exists)->toBeTrue()
        ->and($gradeLevel->section_id)->toBe($section->id);
});

it('refuses a section that does not belong to the school', function (): void {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $foreignSection = SchoolSection::factory()->for($otherSchool)->create();

    (new CreateGradeLevelAction)->execute(new CreateGradeLevelData(
        $school->id, $foreignSection->id, 'G1', 'Grade 1', 1,
    ));
})->throws(ValidationException::class);

it('rejects a duplicate ordinal within the same school (BR-CORE-02-003)', function (): void {
    $school = School::factory()->create();
    $section = SchoolSection::factory()->for($school)->create();

    (new CreateGradeLevelAction)->execute(new CreateGradeLevelData($school->id, $section->id, 'G1', 'Grade 1', 5));
    (new CreateGradeLevelAction)->execute(new CreateGradeLevelData($school->id, $section->id, 'G2', 'Grade 2', 5));
})->throws(ValidationException::class);
