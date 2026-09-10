<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateRollCallPointData;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateRollCallPoint (Book F BRD-02 §2/BR-BRD-02-001).
 */
final class CreateRollCallPointAction extends Action
{
    public function execute(CreateRollCallPointData $data): RollCallPoint
    {
        return $this->transaction(fn (): RollCallPoint => RollCallPoint::create([
            'school_id' => $data->schoolId,
            'hostel_id' => $data->hostelId,
            'code' => $data->code,
            'name' => $data->name,
            'scheduled_time' => $data->scheduledTime,
            'applies_on_days' => $data->appliesOnDays,
            'applies_in_term_only' => $data->appliesInTermOnly,
            'grace_minutes' => $data->graceMinutes,
            'is_mandatory' => $data->isMandatory,
            'escalation_profile_id' => $data->escalationProfileId,
            'is_active' => true,
        ]));
    }
}
