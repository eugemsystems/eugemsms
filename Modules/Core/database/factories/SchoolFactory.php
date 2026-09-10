<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->company().' School',
            'category' => fake()->randomElement(['government', 'council', 'mission', 'trust', 'private']),
            'province' => fake()->randomElement(['Harare', 'Bulawayo', 'Manicaland', 'Mashonaland East']),
            // Explicit, not left to the DB column default: create()'s
            // returned in-memory model reflects exactly the attributes
            // given here, never a DB-computed default, so any code
            // reading $school->primary_colour immediately after creating
            // one (as opposed to a fresh SELECT, e.g. route binding)
            // would otherwise see null instead of the real value.
            'primary_colour' => '#1a3a5c',
            'base_currency' => 'USD',
            'timezone' => 'Africa/Harare',
            'locale' => 'en_ZW',
            'status' => 'active',
        ];
    }
}
