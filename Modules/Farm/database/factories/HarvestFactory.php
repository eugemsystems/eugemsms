<?php

declare(strict_types=1);

namespace Modules\Farm\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Farm\Models\CropCycle;
use Modules\Farm\Models\Harvest;

/**
 * @extends Factory<Harvest>
 */
class HarvestFactory extends Factory
{
    protected $model = Harvest::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'crop_cycle_id' => fn (array $attributes): int => CropCycle::factory()->create(['school_id' => $attributes['school_id']])->id,
            'harvested_on' => now()->toDateString(),
            'quantity_kg' => 600,
            'unit_cost_minor' => 80,
            'currency' => 'USD',
            'destination' => 'store',
            'recorded_by' => User::factory(),
        ];
    }
}
