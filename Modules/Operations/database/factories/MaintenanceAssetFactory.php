<?php

declare(strict_types=1);

namespace Modules\Operations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Models\MaintenanceAsset;

/**
 * @extends Factory<MaintenanceAsset>
 */
class MaintenanceAssetFactory extends Factory
{
    protected $model = MaintenanceAsset::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'MA-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Borehole Pump #1',
            'asset_type' => 'pump',
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'criticality' => 'normal',
            'condition' => 'good',
            'is_active' => true,
        ];
    }

    public function critical(): self
    {
        return $this->state(fn (): array => ['criticality' => 'critical']);
    }
}
