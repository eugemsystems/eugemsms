<?php

declare(strict_types=1);

namespace Modules\Farm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Farm\Models\FarmField;
use Modules\Farm\Models\ProductionUnit;

/**
 * @extends Factory<FarmField>
 */
class FarmFieldFactory extends Factory
{
    protected $model = FarmField::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'production_unit_id' => fn (array $attributes): int => ProductionUnit::factory()->create(['school_id' => $attributes['school_id']])->id,
            'code' => 'F'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Field 1',
            'area_hectares' => 2,
            'is_irrigated' => false,
        ];
    }
}
