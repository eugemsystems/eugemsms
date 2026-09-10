<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Hostel;
use Modules\Core\Models\School;

/**
 * @extends Factory<Hostel>
 */
class HostelFactory extends Factory
{
    protected $model = Hostel::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'NEH',
            'name' => 'Nehanda House',
            'gender' => 'female',
            'capacity' => 0,
            'has_sick_bay' => true,
            'has_prep_room' => true,
            'is_active' => true,
        ];
    }
}
