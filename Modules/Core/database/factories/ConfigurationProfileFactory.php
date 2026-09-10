<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ConfigurationProfile;
use Modules\Core\Models\Tenant;

/**
 * @extends Factory<ConfigurationProfile>
 */
class ConfigurationProfileFactory extends Factory
{
    protected $model = ConfigurationProfile::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'payload' => [],
            'version' => '1.0',
            'created_at' => now(),
        ];
    }
}
