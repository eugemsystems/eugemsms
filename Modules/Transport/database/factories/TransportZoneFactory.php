<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Transport\Models\TransportZone;

/**
 * @extends Factory<TransportZone>
 */
class TransportZoneFactory extends Factory
{
    protected $model = TransportZone::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'Z'.fake()->unique()->numberBetween(1, 9),
            'name' => 'Zone — up to 10km',
            'max_distance_km' => 10,
            'termly_fee_minor' => 5000000,
            'currency' => 'USD',
            'is_active' => true,
        ];
    }
}
