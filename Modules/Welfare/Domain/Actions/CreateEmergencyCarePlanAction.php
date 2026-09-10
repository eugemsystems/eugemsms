<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\CreateEmergencyCarePlanData;
use Modules\Welfare\Models\EmergencyCarePlan;

/**
 * ACT-CreateEmergencyCarePlan (Book G BRD-06 §2/BR-BRD-06-006/007).
 * Deliberately accepts no diagnosis field — the DTO itself has nowhere
 * to put one, matching BR-BRD-06-007's "no diagnosis beyond what is
 * needed to act".
 */
final class CreateEmergencyCarePlanAction extends Action
{
    public function execute(CreateEmergencyCarePlanData $data): EmergencyCarePlan
    {
        return $this->transaction(fn (): EmergencyCarePlan => EmergencyCarePlan::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'condition_id' => $data->conditionId,
            'title' => $data->title,
            'trigger_signs' => $data->triggerSigns,
            'immediate_actions' => $data->immediateActions,
            'medication_location' => $data->medicationLocation,
            'medication_name' => $data->medicationName,
            'do_not_do' => $data->doNotDo,
            'who_to_call' => $data->whoToCall,
            'review_due_on' => $data->reviewDueOn,
            'is_active' => true,
        ]));
    }
}
