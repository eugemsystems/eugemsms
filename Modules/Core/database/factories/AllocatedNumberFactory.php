<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AllocatedNumber;
use Modules\Core\Models\NumberingSeries;
use Modules\Core\Models\School;

/**
 * @extends Factory<AllocatedNumber>
 */
class AllocatedNumberFactory extends Factory
{
    protected $model = AllocatedNumber::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'series_id' => NumberingSeries::factory(),
            'sequence' => fake()->unique()->numberBetween(1, 1_000_000),
            'formatted_number' => fake()->unique()->bothify('RCT/######'),
            'document_type' => 'receipt',
            'status' => 'allocated',
            'allocated_by' => User::factory(),
            'allocated_at' => now(),
        ];
    }
}
