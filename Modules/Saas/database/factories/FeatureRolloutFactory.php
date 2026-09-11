<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\FeatureFlag;
use Modules\Saas\Models\FeatureRollout;

/**
 * @extends Factory<FeatureRollout>
 */
class FeatureRolloutFactory extends Factory
{
    protected $model = FeatureRollout::class;

    public function definition(): array
    {
        return [
            'feature_flag_key' => fn (): string => FeatureFlag::factory()->create()->key,
            'rollout_stage' => 'pilot',
            'pilot_tenant_ids' => [],
            'percentage' => null,
            'started_at' => Carbon::now(),
            'started_by' => User::factory(),
            'notes' => null,
        ];
    }
}
