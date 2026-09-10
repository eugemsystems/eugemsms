<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;

/**
 * @extends Factory<HostelBed>
 */
class HostelBedFactory extends Factory
{
    protected $model = HostelBed::class;

    public function definition(): array
    {
        $room = HostelRoom::factory();

        return [
            'school_id' => $room,
            'room_id' => $room,
            'bed_number' => fake()->unique()->numerify('B##'),
            'bed_type' => 'single',
            'condition_grade' => 'good',
            'is_available' => true,
        ];
    }
}
