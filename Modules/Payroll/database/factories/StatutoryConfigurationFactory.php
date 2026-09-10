<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Models\StatutoryConfiguration;

/**
 * @extends Factory<StatutoryConfiguration>
 */
class StatutoryConfigurationFactory extends Factory
{
    protected $model = StatutoryConfiguration::class;

    public function definition(): array
    {
        return [
            'config_type' => 'zimdef',
            'effective_from' => now()->subYear()->toDateString(),
            'configuration' => ['rate' => '0.01'],
            'requires_confirmation' => false,
            'status' => 'active',
            'created_at' => now(),
        ];
    }
}
