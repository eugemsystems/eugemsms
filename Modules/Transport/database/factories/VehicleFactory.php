<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Transport\Models\Vehicle;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'fleet_number' => 'BUS-'.fake()->unique()->numberBetween(1, 999),
            'registration_number' => strtoupper(fake()->unique()->bothify('??##??')),
            'vehicle_type' => 'bus',
            'seating_capacity' => 65,
            'standing_capacity' => 0,
            'fuel_type' => 'diesel',
            'tank_capacity_litres' => 200,
            'expected_km_per_litre' => 6.0,
            'current_odometer_km' => 0,
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'status' => 'active',
            'is_active' => true,
        ];
    }

    public function grounded(): self
    {
        return $this->state(fn (): array => ['status' => 'grounded', 'grounded_reason' => 'Certificate of fitness expired.']);
    }
}
