<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelRoom;

/**
 * @extends Factory<HostelRoom>
 */
class HostelRoomFactory extends Factory
{
    protected $model = HostelRoom::class;

    public function definition(): array
    {
        $hostel = Hostel::factory();

        return [
            'school_id' => $hostel,
            'hostel_id' => $hostel,
            'room_number' => fake()->unique()->numerify('R##'),
            'room_type' => 'dormitory',
            'bed_count' => 4,
            'condition_grade' => 'good',
            'is_ground_floor' => false,
            'has_power_outlet' => true,
            'is_active' => true,
        ];
    }
}
