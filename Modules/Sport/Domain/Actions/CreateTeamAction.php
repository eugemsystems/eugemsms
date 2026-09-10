<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\DataObjects\CreateTeamData;
use Modules\Sport\Models\Team;

/**
 * ACT-CreateTeam (Book H2 OPS-07 §2).
 */
final class CreateTeamAction extends Action
{
    public function execute(CreateTeamData $data): Team
    {
        return $this->transaction(fn (): Team => Team::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'activity_id' => $data->activityId,
            'name' => $data->name,
            'age_group' => $data->ageGroup,
            'level' => $data->level,
            'coach_staff_id' => $data->coachStaffId,
            'captain_student_id' => $data->captainStudentId,
            'is_active' => true,
        ]));
    }
}
