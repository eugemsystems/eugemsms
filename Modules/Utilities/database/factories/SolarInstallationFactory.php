<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Utilities\Models\SolarInstallation;

/**
 * @extends Factory<SolarInstallation>
 */
class SolarInstallationFactory extends Factory
{
    protected $model = SolarInstallation::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'SOLAR-'.fake()->unique()->numberBetween(1, 99),
            'name' => 'Rooftop Solar Array',
            'capacity_kwp' => 50,
            'battery_capacity_kwh' => 100,
            'serves_scope' => 'whole_school',
            'status' => 'operational',
        ];
    }
}
