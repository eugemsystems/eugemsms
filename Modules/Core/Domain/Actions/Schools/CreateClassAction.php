<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\CreateClassData;
use Modules\Core\Domain\Events\Schools\ClassCreated;
use Modules\Core\Models\SchoolClass;

/**
 * ACT-CreateClass (Book A CORE-02 §3). BR-CORE-02-004: a class always
 * belongs to exactly one academic year — classes are created fresh each
 * year, never carried across.
 */
final class CreateClassAction extends Action
{
    public function execute(CreateClassData $data): SchoolClass
    {
        Validator::make(
            [
                'academic_year_id' => $data->academicYearId,
                'grade_level_id' => $data->gradeLevelId,
                'code' => $data->code,
                'name' => $data->name,
                'capacity' => $data->capacity,
            ],
            [
                'academic_year_id' => ['required', 'integer', 'exists:academic_years,id,school_id,'.$data->schoolId],
                'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id,school_id,'.$data->schoolId],
                'code' => [
                    'required', 'string', 'max:30',
                    'unique:school_classes,code,NULL,id,school_id,'.$data->schoolId.',academic_year_id,'.$data->academicYearId,
                ],
                'name' => ['required', 'string', 'max:80'],
                'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            ],
        )->validate();

        return $this->transaction(function () use ($data): SchoolClass {
            $class = SchoolClass::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'grade_level_id' => $data->gradeLevelId,
                'code' => $data->code,
                'name' => $data->name,
                'stream_label' => $data->streamLabel,
                'class_teacher_id' => $data->classTeacherId,
                'assistant_teacher_id' => $data->assistantTeacherId,
                'room_id' => $data->roomId,
                'capacity' => $data->capacity,
                'is_active' => true,
            ]);

            event(new ClassCreated($class));

            return $class;
        });
    }
}
