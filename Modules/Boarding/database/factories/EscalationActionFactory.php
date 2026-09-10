<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\EscalationAction;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Core\Models\School;

/**
 * @extends Factory<EscalationAction>
 */
class EscalationActionFactory extends Factory
{
    protected $model = EscalationAction::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'incident_id' => MissingLearnerIncident::factory()->state(['school_id' => $school]),
            'step_number' => 1,
            'action_type' => 'notified',
            'occurred_at' => now(),
        ];
    }
}
