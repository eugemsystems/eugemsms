<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\EscalationProfile;
use Modules\Boarding\Models\EscalationStep;

/**
 * @extends Factory<EscalationStep>
 */
class EscalationStepFactory extends Factory
{
    protected $model = EscalationStep::class;

    public function definition(): array
    {
        return [
            'profile_id' => EscalationProfile::factory(),
            'step_number' => 1,
            'delay_minutes' => 0,
            'notify_guardians' => false,
            'channels' => ['push'],
            'requires_acknowledgement' => true,
            'requires_action_record' => true,
            'message_template_key' => 'boarding.missing_learner_step',
        ];
    }
}
