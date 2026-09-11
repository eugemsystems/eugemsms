<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Saas\Models\ReleaseDeployment;

/**
 * @extends Factory<ReleaseDeployment>
 */
class ReleaseDeploymentFactory extends Factory
{
    protected $model = ReleaseDeployment::class;

    public function definition(): array
    {
        return [
            'version' => '1.'.fake()->numberBetween(1, 99).'.0',
            'deployment_stage' => 'staging',
            'canary_tenant_ids' => null,
            'migration_status' => 'pending',
            'started_at' => Carbon::now(),
            'completed_at' => null,
            'rollback_available' => true,
        ];
    }
}
