<?php

declare(strict_types=1);

namespace Modules\Fiscal\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Fiscal\Models\FiscalisationRule;

/**
 * @extends Factory<FiscalisationRule>
 */
class FiscalisationRuleFactory extends Factory
{
    protected $model = FiscalisationRule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'rule_name' => 'Tuckshop sales are standard-rated',
            'source_type' => 'fee_component',
            'is_fiscalisable' => true,
            'tax_type' => 'standard',
            'tax_rate_percent' => 15,
            'priority' => 100,
            'is_active' => true,
        ];
    }
}
