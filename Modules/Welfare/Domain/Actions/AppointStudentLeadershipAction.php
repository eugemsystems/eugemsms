<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\AppointStudentLeadershipData;
use Modules\Welfare\Models\StudentLeadership;

/**
 * ACT-AppointStudentLeadership (Book G BRD-07 §2/BR-BRD-07-020).
 */
final class AppointStudentLeadershipAction extends Action
{
    public function execute(AppointStudentLeadershipData $data): StudentLeadership
    {
        return $this->transaction(fn (): StudentLeadership => StudentLeadership::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'student_id' => $data->studentId,
            'role_title' => $data->roleTitle,
            'scope_type' => $data->scopeType,
            'scope_id' => $data->scopeId,
            'granted_permissions' => $data->grantedPermissions,
            'starts_on' => $data->startsOn->toDateString(),
            'appointed_by' => $data->appointedByUserId,
            'status' => 'active',
        ]));
    }
}
