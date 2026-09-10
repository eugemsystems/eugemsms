<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateTeachingGroupData;
use Modules\Academic\Models\TeachingGroup;
use Modules\Core\Domain\Actions\Action;

final class CreateTeachingGroupAction extends Action
{
    public function execute(CreateTeachingGroupData $data): TeachingGroup
    {
        return $this->transaction(fn (): TeachingGroup => TeachingGroup::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'subject_id' => $data->subjectId,
            'grade_level_id' => $data->gradeLevelId,
            'code' => $data->code,
            'name' => $data->name,
            'set_level' => $data->setLevel,
            'teacher_staff_id' => $data->teacherStaffId,
            'room_id' => $data->roomId,
            'capacity' => $data->capacity,
            'current_count' => 0,
            'is_active' => true,
        ]));
    }
}
