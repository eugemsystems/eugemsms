<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\CreateGradeLevelData;
use Modules\Core\Models\GradeLevel;

/**
 * ACT-CreateGradeLevel (Book A CORE-02 §3). BR-CORE-02-002: `sectionId`
 * must name a section that already exists for this school — a school
 * must have at least one section before any grade level can be created.
 * BR-CORE-02-003: `ordinal` is unique per school and defines the
 * promotion sequence.
 */
final class CreateGradeLevelAction extends Action
{
    public function execute(CreateGradeLevelData $data): GradeLevel
    {
        Validator::make(
            [
                'section_id' => $data->sectionId,
                'code' => $data->code,
                'name' => $data->name,
                'ordinal' => $data->ordinal,
            ],
            [
                'section_id' => ['required', 'integer', 'exists:school_sections,id,school_id,'.$data->schoolId],
                'code' => [
                    'required', 'string', 'max:20',
                    'unique:grade_levels,code,NULL,id,school_id,'.$data->schoolId,
                ],
                'name' => ['required', 'string', 'max:60'],
                'ordinal' => [
                    'required', 'integer', 'min:0', 'max:13',
                    'unique:grade_levels,ordinal,NULL,id,school_id,'.$data->schoolId,
                ],
            ],
        )->validate();

        return $this->transaction(fn (): GradeLevel => GradeLevel::create([
            'school_id' => $data->schoolId,
            'section_id' => $data->sectionId,
            'code' => $data->code,
            'name' => $data->name,
            'ordinal' => $data->ordinal,
            'is_exam_level' => $data->isExamLevel,
            'is_entry_level' => $data->isEntryLevel,
            'is_exit_level' => $data->isExitLevel,
            'capacity' => $data->capacity,
            'is_active' => true,
        ]));
    }
}
