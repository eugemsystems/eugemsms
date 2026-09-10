<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\BehaviourTriggerRule;
use Modules\Welfare\Models\SanctionType;

/**
 * @extends Factory<BehaviourTriggerRule>
 */
class BehaviourTriggerRuleFactory extends Factory
{
    protected $model = BehaviourTriggerRule::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'name' => 'Repeated demerits',
            'trigger_type' => 'points_threshold',
            'demerit_threshold' => 15,
            'window_days' => 30,
            'suggested_sanction_id' => SanctionType::factory()->create(['school_id' => $school]),
            'is_automatic' => false,
            'is_active' => true,
        ];
    }
}
