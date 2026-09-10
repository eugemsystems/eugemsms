<?php

use Modules\Core\Domain\Actions\Schools\CloneSchoolConfigurationAction;
use Modules\Core\Domain\DataObjects\Schools\CloneConfigData;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\House;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Tenant;

it('clones sections, grade levels, and houses into the target school', function (): void {
    $tenant = Tenant::factory()->create();
    $source = School::factory()->for($tenant)->create();
    $target = School::factory()->for($tenant)->create();

    $section = SchoolSection::factory()->for($source)->create(['code' => 'JUN', 'name' => 'Junior']);
    GradeLevel::factory()->for($source)->for($section, 'section')->create(['code' => 'G3', 'ordinal' => 3]);
    House::factory()->for($source)->create(['code' => 'CHI']);

    $result = (new CloneSchoolConfigurationAction)->execute(new CloneConfigData(
        sourceSchoolId: $source->id,
        targetSchoolId: $target->id,
        actingUserId: 1,
    ));

    expect($result->sectionsCreated)->toBe(1)
        ->and($result->gradeLevelsCreated)->toBe(1)
        ->and($result->housesCreated)->toBe(1);

    $clonedSection = SchoolSection::withoutGlobalScopes()->where('school_id', $target->id)->sole();
    expect($clonedSection->code)->toBe('JUN');

    $clonedGradeLevel = GradeLevel::withoutGlobalScopes()->where('school_id', $target->id)->sole();
    expect($clonedGradeLevel->code)->toBe('G3')
        ->and($clonedGradeLevel->section_id)->toBe($clonedSection->id);

    expect(House::withoutGlobalScopes()->where('school_id', $target->id)->sole()->code)->toBe('CHI');

    // The source's own rows are untouched — this is a copy, not a move.
    expect(SchoolSection::withoutGlobalScopes()->where('school_id', $source->id)->count())->toBe(1);
});

it('skips grade levels and houses when their flags are off', function (): void {
    $tenant = Tenant::factory()->create();
    $source = School::factory()->for($tenant)->create();
    $target = School::factory()->for($tenant)->create();

    SchoolSection::factory()->for($source)->create();
    House::factory()->for($source)->create();

    $result = (new CloneSchoolConfigurationAction)->execute(new CloneConfigData(
        sourceSchoolId: $source->id,
        targetSchoolId: $target->id,
        actingUserId: 1,
        cloneGradeLevels: false,
        cloneHouses: false,
    ));

    expect($result->sectionsCreated)->toBe(1)
        ->and($result->gradeLevelsCreated)->toBe(0)
        ->and($result->housesCreated)->toBe(0);
});
