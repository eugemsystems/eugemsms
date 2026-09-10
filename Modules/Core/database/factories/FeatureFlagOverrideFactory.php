<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\FeatureFlag;
use Modules\Core\Models\FeatureFlagOverride;

/**
 * @extends Factory<FeatureFlagOverride>
 */
class FeatureFlagOverrideFactory extends Factory
{
    protected $model = FeatureFlagOverride::class;

    public function definition(): array
    {
        return [
            'feature_flag_id' => FeatureFlag::factory(),
            'scope_type' => 'school',
            'scope_id' => 1,
            'is_enabled' => true,
        ];
    }
}
