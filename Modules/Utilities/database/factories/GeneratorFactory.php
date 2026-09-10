<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Utilities\Models\Generator;

/**
 * @extends Factory<Generator>
 */
class GeneratorFactory extends Factory
{
    protected $model = Generator::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'GEN-'.fake()->unique()->numberBetween(1, 99),
            'name' => 'Main Standby Generator',
            'capacity_kva' => 100,
            'fuel_type' => 'diesel',
            'tank_capacity_litres' => 500,
            'expected_litres_per_hour' => 15,
            'serves_scope' => 'whole_school',
            'current_hours' => 0,
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'status' => 'standby',
        ];
    }
}
