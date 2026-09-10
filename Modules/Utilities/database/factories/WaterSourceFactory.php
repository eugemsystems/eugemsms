<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Utilities\Models\WaterSource;

/**
 * @extends Factory<WaterSource>
 */
class WaterSourceFactory extends Factory
{
    protected $model = WaterSource::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'BH-'.fake()->unique()->numberBetween(1, 99),
            'name' => 'Main Borehole',
            'source_type' => 'borehole',
            'yield_litres_per_hour' => 2000,
            'storage_capacity_litres' => 50000,
            'status' => 'operational',
            'water_quality_status' => 'potable',
        ];
    }
}
