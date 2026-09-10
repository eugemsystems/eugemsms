<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\FeatureFlag;

/**
 * @extends Factory<FeatureFlag>
 */
class FeatureFlagFactory extends Factory
{
    protected $model = FeatureFlag::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'is_globally_enabled' => false,
            'rollout_percentage' => 0,
        ];
    }
}
