<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\MealRequisitionLine;
use Modules\Boarding\Models\MealService;

/**
 * @extends Factory<MealRequisitionLine>
 */
class MealRequisitionLineFactory extends Factory
{
    protected $model = MealRequisitionLine::class;

    public function definition(): array
    {
        $service = MealService::factory();

        return [
            'school_id' => $service,
            'meal_service_id' => $service,
            'inventory_item_id' => fake()->numberBetween(1, 1000),
            'required_quantity' => '10.0000',
            'unit' => 'kg',
        ];
    }
}
