<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\House;
use Modules\Core\Models\School;

/**
 * @extends Factory<House>
 */
class HouseFactory extends Factory
{
    protected $model = House::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->unique()->lastName(),
            'colour' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
