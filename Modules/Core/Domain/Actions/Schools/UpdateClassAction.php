<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\UpdateClassData;
use Modules\Core\Models\SchoolClass;

/**
 * ACT-UpdateClass (Book A CORE-02 §5, admin UI follow-up). Does not
 * change `academic_year_id`/`grade_level_id` — BR-CORE-02-004: a class
 * belongs to exactly one year and grade level for its whole life;
 * moving one means creating a new class in the target year, not editing
 * this one in place.
 */
final class UpdateClassAction extends Action
{
    public function execute(UpdateClassData $data): SchoolClass
    {
        $class = SchoolClass::withoutGlobalScopes()
            ->where('id', $data->classId)
            ->where('school_id', $data->schoolId)
            ->firstOrFail();

        Validator::make(
            [
                'code' => $data->code,
                'name' => $data->name,
                'capacity' => $data->capacity,
            ],
            [
                'code' => [
                    'required', 'string', 'max:30',
                    'unique:school_classes,code,'.$class->id.',id,school_id,'.$data->schoolId.',academic_year_id,'.$class->academic_year_id,
                ],
                'name' => ['required', 'string', 'max:80'],
                'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            ],
        )->validate();

        return $this->transaction(function () use ($class, $data): SchoolClass {
            $class->update([
                'code' => $data->code,
                'name' => $data->name,
                'stream_label' => $data->streamLabel,
                'class_teacher_id' => $data->classTeacherId,
                'assistant_teacher_id' => $data->assistantTeacherId,
                'room_id' => $data->roomId,
                'capacity' => $data->capacity,
            ]);

            return $class;
        });
    }
}
