<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intelligence\Models\RiskIndicator;

/**
 * @extends Factory<RiskIndicator>
 */
class RiskIndicatorFactory extends Factory
{
    protected $model = RiskIndicator::class;

    public function definition(): array
    {
        return [
            'key' => 'indicator_'.$this->faker->unique()->numerify('###'),
            'module_code' => 'TEST',
            'applies_to' => 'learner',
            'plain_language_description' => 'A test indicator.',
            'default_weight' => 10,
        ];
    }
}
