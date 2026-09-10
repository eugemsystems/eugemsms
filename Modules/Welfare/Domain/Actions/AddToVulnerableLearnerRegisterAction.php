<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\AddToVulnerableLearnerRegisterData;
use Modules\Welfare\Models\VulnerableLearnerRegistration;

/**
 * ACT-AddToVulnerableLearnerRegister (Book G BRD-08 §2/BR-BRD-08-018).
 */
final class AddToVulnerableLearnerRegisterAction extends Action
{
    public function execute(AddToVulnerableLearnerRegisterData $data): VulnerableLearnerRegistration
    {
        return $this->transaction(fn (): VulnerableLearnerRegistration => VulnerableLearnerRegistration::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'vulnerability_type' => $data->vulnerabilityType,
            'identified_at' => $data->identifiedAt,
            'identified_by' => $data->identifiedByUserId,
            'support_plan' => $data->supportPlan,
            'assigned_mentor_id' => $data->assignedMentorId,
            'review_frequency_days' => $data->reviewFrequencyDays,
            'next_review_on' => $data->identifiedAt->copy()->addDays($data->reviewFrequencyDays)->toDateString(),
            'status' => 'active',
        ]));
    }
}
