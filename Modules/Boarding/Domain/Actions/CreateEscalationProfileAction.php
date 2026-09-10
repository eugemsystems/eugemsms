<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateEscalationProfileData;
use Modules\Boarding\Models\EscalationProfile;
use Modules\Boarding\Models\EscalationStep;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateEscalationProfile (Book F BRD-02 §2/§3 ⭐). Takes the
 * profile and its complete step ladder together, mirroring
 * `CreateGradingScaleAction`/`CreateProjectRubricAction`'s own
 * reasoning for a configuration set that only makes sense whole.
 */
final class CreateEscalationProfileAction extends Action
{
    public function execute(CreateEscalationProfileData $data): EscalationProfile
    {
        return $this->transaction(function () use ($data): EscalationProfile {
            $profile = EscalationProfile::create([
                'school_id' => $data->schoolId,
                'name' => $data->name,
                'description' => $data->description,
                'is_default' => $data->isDefault,
            ]);

            foreach ($data->steps as $step) {
                EscalationStep::create([
                    'profile_id' => $profile->id,
                    'step_number' => $step->stepNumber,
                    'delay_minutes' => $step->delayMinutes,
                    'notify_role_id' => $step->notifyRoleId,
                    'notify_staff_id' => $step->notifyStaffId,
                    'notify_guardians' => $step->notifyGuardians,
                    'channels' => $step->channels,
                    'requires_acknowledgement' => $step->requiresAcknowledgement,
                    'requires_action_record' => $step->requiresActionRecord,
                    'message_template_key' => $step->messageTemplateKey,
                ]);
            }

            return $profile;
        });
    }
}
