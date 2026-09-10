<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;

/**
 * @extends Factory<CostCentre>
 */
class CostCentreFactory extends Factory
{
    protected $model = CostCentre::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => fake()->unique()->lexify('???'),
            'name' => fake()->words(2, true),
            'is_profit_centre' => false,
            'is_active' => true,
        ];
    }
}
