<?php

declare(strict_types=1);

namespace Modules\Farm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Farm\Models\ProductionUnit;
use Modules\Finance\Models\CostCentre;

/**
 * @extends Factory<ProductionUnit>
 */
class ProductionUnitFactory extends Factory
{
    protected $model = ProductionUnit::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'CROP-'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Maize Field A',
            'unit_type' => 'crop',
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'area_hectares' => 5,
            'is_active' => true,
        ];
    }
}
