<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\CreateSanctionTypeData;
use Modules\Welfare\Models\SanctionType;

/**
 * ACT-CreateSanctionType (Book G BRD-07 §2).
 */
final class CreateSanctionTypeAction extends Action
{
    public function execute(CreateSanctionTypeData $data): SanctionType
    {
        return $this->transaction(fn (): SanctionType => SanctionType::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'severity_level' => $data->severityLevel,
            'requires_guardian_meeting' => $data->requiresGuardianMeeting,
            'requires_committee' => $data->requiresCommittee,
            'removes_from_lessons' => $data->removesFromLessons,
            'removes_from_campus' => $data->removesFromCampus,
            'max_duration_days' => $data->maxDurationDays,
            'appealable' => $data->appealable,
            'appeal_window_days' => $data->appealWindowDays,
            'is_active' => true,
        ]));
    }
}
