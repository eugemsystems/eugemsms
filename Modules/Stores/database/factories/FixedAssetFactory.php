<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\FixedAsset;

/**
 * @extends Factory<FixedAsset>
 */
class FixedAssetFactory extends Factory
{
    protected $model = FixedAsset::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'asset_tag' => 'AST-'.fake()->unique()->numberBetween(10000, 99999),
            'category_id' => fn (array $attributes): int => AssetCategory::factory()->create(['school_id' => $attributes['school_id']])->id,
            'name' => 'Dell Latitude Laptop',
            'acquisition_date' => now()->toDateString(),
            'acquisition_cost_minor' => 1200000,
            'currency' => 'USD',
            'base_cost_minor' => 1200000,
            'acquisition_source' => 'purchase',
            'is_depreciable' => true,
            'depreciation_method' => 'straight_line',
            'useful_life_years' => 5,
            'residual_value_minor' => 200000,
            'depreciation_start_date' => now()->startOfMonth()->addMonth()->toDateString(),
            'accumulated_depreciation_minor' => 0,
            'net_book_value_minor' => 1200000,
            'fully_depreciated' => false,
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'status' => 'active',
            'condition' => 'good',
        ];
    }
}
