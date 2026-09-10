<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\CloneConfigData;
use Modules\Core\Domain\DataObjects\Schools\CloneResult;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\House;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;

/**
 * ACT-CloneSchoolConfiguration (Book A CORE-02 §3). Copies one school's
 * structural setup — sections, grade levels, houses — into another,
 * for a tenant standing up a second school with the same shape. Never
 * touches enrolment, staff, or academic-year data.
 *
 * Reads from the source school via the `forSchools()` scope rather than
 * its `HasMany` relations: the source is very often *not* the caller's
 * ambient `SchoolContext` (you're standing inside the new target school
 * while cloning FROM an existing one), and `BelongsToSchool`'s ordinary
 * global scope would silently return nothing in that case.
 * `forSchools()` requires the acting user be assigned to the source
 * school (BR-GLOBAL-013) rather than the `core.system.bypass_school_scope`
 * permission `withoutSchoolScope()` needs — a permission CORE-05 hasn't
 * shipped yet.
 */
final class CloneSchoolConfigurationAction extends Action
{
    public function execute(CloneConfigData $data): CloneResult
    {
        $source = School::query()->findOrFail($data->sourceSchoolId);
        $target = School::query()->findOrFail($data->targetSchoolId);

        return $this->transaction(function () use ($source, $target, $data): CloneResult {
            $sectionMap = [];
            $sectionsCreated = 0;
            $gradeLevelsCreated = 0;
            $housesCreated = 0;

            if ($data->cloneSections || $data->cloneGradeLevels) {
                foreach (SchoolSection::query()->forSchools([$source->id])->get() as $section) {
                    /** @var SchoolSection $clone */
                    $clone = SchoolSection::create([
                        'school_id' => $target->id,
                        'code' => $section->code,
                        'name' => $section->name,
                        'type' => $section->type,
                        'sort_order' => $section->sort_order,
                        'is_active' => true,
                    ]);

                    $sectionMap[$section->id] = $clone->id;
                    $sectionsCreated++;
                }
            }

            if ($data->cloneGradeLevels) {
                foreach (GradeLevel::query()->forSchools([$source->id])->get() as $gradeLevel) {
                    GradeLevel::create([
                        'school_id' => $target->id,
                        'section_id' => $sectionMap[$gradeLevel->section_id] ?? $gradeLevel->section_id,
                        'code' => $gradeLevel->code,
                        'name' => $gradeLevel->name,
                        'ordinal' => $gradeLevel->ordinal,
                        'is_exam_level' => $gradeLevel->is_exam_level,
                        'is_entry_level' => $gradeLevel->is_entry_level,
                        'is_exit_level' => $gradeLevel->is_exit_level,
                        'capacity' => $gradeLevel->capacity,
                        'is_active' => true,
                    ]);

                    $gradeLevelsCreated++;
                }
            }

            if ($data->cloneHouses) {
                foreach (House::query()->forSchools([$source->id])->get() as $house) {
                    House::create([
                        'school_id' => $target->id,
                        'code' => $house->code,
                        'name' => $house->name,
                        'colour' => $house->colour,
                        'motto' => $house->motto,
                        'is_active' => true,
                    ]);

                    $housesCreated++;
                }
            }

            return new CloneResult($sectionsCreated, $gradeLevelsCreated, $housesCreated);
        });
    }
}
