<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Security\Models\PatrolRoute;

/**
 * @extends Factory<PatrolRoute>
 */
class PatrolRouteFactory extends Factory
{
    protected $model = PatrolRoute::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'RT-'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Perimeter Route',
            'checkpoint_ids' => [],
            'frequency' => 'two_hourly',
            'is_active' => true,
        ];
    }
}
