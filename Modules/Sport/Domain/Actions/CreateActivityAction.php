<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\DataObjects\CreateActivityData;
use Modules\Sport\Models\Activity;

/**
 * ACT-CreateActivity (Book H2 OPS-07 §2).
 */
final class CreateActivityAction extends Action
{
    public function execute(CreateActivityData $data): Activity
    {
        return $this->transaction(fn (): Activity => Activity::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'activity_type' => $data->activityType,
            'season' => $data->season,
            'gender_scope' => $data->genderScope,
            'min_grade_ordinal' => $data->minGradeOrdinal,
            'max_grade_ordinal' => $data->maxGradeOrdinal,
            'coach_staff_id' => $data->coachStaffId,
            'fee_component_id' => $data->feeComponentId,
            'requires_medical_clearance' => $data->requiresMedicalClearance,
            'requires_guardian_consent' => $data->requiresGuardianConsent,
            'max_participants' => $data->maxParticipants,
            'venue_id' => $data->venueId,
            'is_active' => true,
        ]));
    }
}
