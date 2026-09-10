<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelWing;

/**
 * @extends Factory<HostelWing>
 */
class HostelWingFactory extends Factory
{
    protected $model = HostelWing::class;

    public function definition(): array
    {
        $hostel = Hostel::factory();

        return [
            'school_id' => $hostel,
            'hostel_id' => $hostel,
            'code' => 'A',
            'name' => 'A Wing',
            'is_active' => true,
        ];
    }
}
