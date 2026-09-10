<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Transport\Models\FuelLog;
use Modules\Transport\Models\Vehicle;

/**
 * @extends Factory<FuelLog>
 */
class FuelLogFactory extends Factory
{
    protected $model = FuelLog::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'vehicle_id' => fn (array $attributes): int => Vehicle::factory()->create(['school_id' => $attributes['school_id']])->id,
            'fuelled_at' => now(),
            'odometer_km' => 1000,
            'litres' => 50,
            'unit_price_minor' => 150,
            'total_cost_minor' => 7500,
            'currency' => 'USD',
            'source' => 'filling_station',
            'is_anomaly' => false,
        ];
    }
}
