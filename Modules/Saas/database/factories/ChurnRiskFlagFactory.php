<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Models\ChurnRiskFlag;

/**
 * @extends Factory<ChurnRiskFlag>
 */
class ChurnRiskFlagFactory extends Factory
{
    protected $model = ChurnRiskFlag::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'flagged_at' => Carbon::now(),
            'contributing_factors' => [],
            'status' => 'open',
            'assigned_to' => null,
        ];
    }
}
