<?php

declare(strict_types=1);

namespace Modules\Facilities\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Facilities\Models\BookableResource;
use Modules\Facilities\Models\ResourceBooking;

/**
 * @extends Factory<ResourceBooking>
 */
class ResourceBookingFactory extends Factory
{
    protected $model = ResourceBooking::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
                'financial_state' => 'open',
            ])->id,
            'booking_number' => 'BKG-'.fake()->unique()->numberBetween(10000, 99999),
            'resource_id' => fn (array $attributes): int => BookableResource::factory()->create(['school_id' => $attributes['school_id']])->id,
            'booking_type' => 'internal',
            'purpose' => 'Staff meeting',
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'status' => 'requested',
        ];
    }
}
