<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\Venue;
use Modules\Core\Models\School;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('R???')),
            'name' => 'Classroom',
            'venue_type' => 'classroom',
            'capacity' => 40,
            'is_bookable_externally' => false,
            'is_active' => true,
        ];
    }
}
