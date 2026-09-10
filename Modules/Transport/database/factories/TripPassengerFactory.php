<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Transport\Models\Trip;
use Modules\Transport\Models\TripPassenger;

/**
 * @extends Factory<TripPassenger>
 */
class TripPassengerFactory extends Factory
{
    protected $model = TripPassenger::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'trip_id' => fn (array $attributes): int => Trip::factory()->create(['school_id' => $attributes['school_id']])->id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'status' => 'expected',
        ];
    }
}
