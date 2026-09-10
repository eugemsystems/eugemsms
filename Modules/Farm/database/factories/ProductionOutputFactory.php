<?php

declare(strict_types=1);

namespace Modules\Farm\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Farm\Models\ProductionOutput;
use Modules\Farm\Models\ProductionUnit;

/**
 * @extends Factory<ProductionOutput>
 */
class ProductionOutputFactory extends Factory
{
    protected $model = ProductionOutput::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'production_unit_id' => fn (array $attributes): int => ProductionUnit::factory()->create(['school_id' => $attributes['school_id']])->id,
            'output_date' => now()->toDateString(),
            'output_type' => 'milk',
            'quantity' => 100,
            'unit' => 'litres',
            'currency' => 'USD',
            'destination' => 'kitchen',
            'recorded_by' => User::factory(),
        ];
    }
}
