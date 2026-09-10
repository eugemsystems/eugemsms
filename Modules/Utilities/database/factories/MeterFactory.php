<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\UtilityAccount;

/**
 * @extends Factory<Meter>
 */
class MeterFactory extends Factory
{
    protected $model = Meter::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'utility_account_id' => fn (array $attributes): int => UtilityAccount::factory()->create(['school_id' => $attributes['school_id']])->id,
            'meter_number' => 'MTR-'.fake()->unique()->numberBetween(10000, 99999),
            'meter_type' => 'electricity_prepaid',
            'location' => 'Main switchroom',
            'serves_scope' => 'whole_school',
            'unit' => 'kWh',
            'multiplier' => 1,
            'current_balance_units' => 0,
            'is_active' => true,
        ];
    }
}
