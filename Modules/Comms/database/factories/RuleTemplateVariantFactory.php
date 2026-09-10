<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\RuleTemplateVariant;

/**
 * @extends Factory<RuleTemplateVariant>
 */
class RuleTemplateVariantFactory extends Factory
{
    protected $model = RuleTemplateVariant::class;

    public function definition(): array
    {
        return [
            'rule_id' => AutomationRule::factory(),
            'variant_key' => 'A',
            'template_key' => 'fee.overdue.stage_2',
            'weight_percent' => 50,
            'sent_count' => 0,
            'opened_count' => 0,
            'response_count' => 0,
        ];
    }
}
