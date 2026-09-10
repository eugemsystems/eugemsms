<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\RuleCondition;

/**
 * @extends Factory<RuleCondition>
 */
class RuleConditionFactory extends Factory
{
    protected $model = RuleCondition::class;

    public function definition(): array
    {
        return [
            'rule_id' => AutomationRule::factory(),
            'group_id' => 1,
            'group_logic' => 'AND',
            'field' => 'invoice.balance_minor',
            'operator' => 'gt',
            'value' => 0,
            'sort_order' => 1,
        ];
    }
}
