<?php

declare(strict_types=1);

namespace Modules\Farm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Farm\Models\CropCycle;
use Modules\Farm\Models\CropInput;

/**
 * @extends Factory<CropInput>
 */
class CropInputFactory extends Factory
{
    protected $model = CropInput::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'crop_cycle_id' => fn (array $attributes): int => CropCycle::factory()->create(['school_id' => $attributes['school_id']])->id,
            'input_type' => 'fertiliser',
            'description' => 'Compound D',
            'quantity' => 50,
            'unit' => 'kg',
            'applied_on' => now()->toDateString(),
            'cost_minor' => 5000,
            'currency' => 'USD',
        ];
    }
}
