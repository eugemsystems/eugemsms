<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Tenant;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(2),
            'type' => 'independent',
            'contact_email' => fake()->companyEmail(),
            'country' => 'ZW',
            'status' => 'active',
            'is_group_reporting_enabled' => false,
        ];
    }
}
