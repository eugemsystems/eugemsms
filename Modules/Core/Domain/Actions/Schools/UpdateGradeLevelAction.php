<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\UpdateGradeLevelData;
use Modules\Core\Models\GradeLevel;

/**
 * ACT-UpdateGradeLevel (Book A CORE-02 §5, admin UI follow-up).
 * BR-CORE-02-003: `ordinal` stays unique per school.
 */
final class UpdateGradeLevelAction extends Action
{
    public function execute(UpdateGradeLevelData $data): GradeLevel
    {
        $gradeLevel = GradeLevel::withoutGlobalScopes()
            ->where('id', $data->gradeLevelId)
            ->where('school_id', $data->schoolId)
            ->firstOrFail();

        Validator::make(
            [
                'code' => $data->code,
                'name' => $data->name,
                'ordinal' => $data->ordinal,
            ],
            [
                'code' => [
                    'required', 'string', 'max:20',
                    'unique:grade_levels,code,'.$gradeLevel->id.',id,school_id,'.$data->schoolId,
                ],
                'name' => ['required', 'string', 'max:60'],
                'ordinal' => [
                    'required', 'integer', 'min:0', 'max:13',
                    'unique:grade_levels,ordinal,'.$gradeLevel->id.',id,school_id,'.$data->schoolId,
                ],
            ],
        )->validate();

        return $this->transaction(function () use ($gradeLevel, $data): GradeLevel {
            $gradeLevel->update([
                'code' => $data->code,
                'name' => $data->name,
                'ordinal' => $data->ordinal,
                'is_exam_level' => $data->isExamLevel,
                'is_entry_level' => $data->isEntryLevel,
                'is_exit_level' => $data->isExitLevel,
                'capacity' => $data->capacity,
            ]);

            return $gradeLevel;
        });
    }
}
