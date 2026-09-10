<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\FeeStructure;
use Modules\Finance\Models\FeeStructureRule;

/**
 * @extends Factory<FeeStructureRule>
 */
class FeeStructureRuleFactory extends Factory
{
    protected $model = FeeStructureRule::class;

    public function definition(): array
    {
        return [
            'structure_id' => FeeStructure::factory(),
            'attribute' => 'enrolment_type',
            'operator' => 'equals',
            'value' => 'FULL_TIME',
        ];
    }
}
