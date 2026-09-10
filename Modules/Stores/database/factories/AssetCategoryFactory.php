<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Stores\Models\AssetCategory;

/**
 * @extends Factory<AssetCategory>
 */
class AssetCategoryFactory extends Factory
{
    protected $model = AssetCategory::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'ICT',
            'name' => 'ICT Equipment',
            'asset_account_id' => fn (array $attributes): int => Account::factory()->create(['school_id' => $attributes['school_id']])->id,
            'accum_depreciation_account_id' => fn (array $attributes): int => Account::factory()->create(['school_id' => $attributes['school_id']])->id,
            'depreciation_expense_account_id' => fn (array $attributes): int => Account::factory()->expense()->create(['school_id' => $attributes['school_id']])->id,
            'disposal_account_id' => fn (array $attributes): int => Account::factory()->create(['school_id' => $attributes['school_id']])->id,
            'default_method' => 'straight_line',
            'default_useful_life_years' => 5,
            'default_residual_percent' => 0,
            'is_depreciable' => true,
            'verification_frequency_months' => 12,
        ];
    }
}
