<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\RuleExecution;
use Modules\Core\Models\School;

/**
 * @extends Factory<RuleExecution>
 */
class RuleExecutionFactory extends Factory
{
    protected $model = RuleExecution::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'rule_id' => fn (array $attributes): int => AutomationRule::factory()->create(['school_id' => $attributes['school_id']])->id,
            'trigger_source' => 'scan',
            'subject_type' => 'invoice',
            'subject_id' => 1,
            'matched' => true,
            'skip_reason' => null,
            'notification_id' => null,
            'variant_key' => null,
            'executed_at' => now(),
        ];
    }
}
