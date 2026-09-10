<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Transport\Models\Driver;
use Modules\Transport\Models\Trip;
use Modules\Transport\Models\Vehicle;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'trip_date' => now()->toDateString(),
            'trip_type' => 'route',
            'vehicle_id' => fn (array $attributes): int => Vehicle::factory()->create(['school_id' => $attributes['school_id']])->id,
            'driver_id' => fn (array $attributes): int => Driver::factory()->create(['school_id' => $attributes['school_id']])->id,
            'status' => 'scheduled',
        ];
    }
}
