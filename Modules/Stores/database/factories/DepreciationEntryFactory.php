<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\DepreciationEntry;
use Modules\Stores\Models\DepreciationRun;
use Modules\Stores\Models\FixedAsset;

/**
 * @extends Factory<DepreciationEntry>
 */
class DepreciationEntryFactory extends Factory
{
    protected $model = DepreciationEntry::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'run_id' => fn (array $attributes): int => DepreciationRun::factory()->create(['school_id' => $attributes['school_id']])->id,
            'asset_id' => fn (array $attributes): int => FixedAsset::factory()->create(['school_id' => $attributes['school_id']])->id,
            'opening_nbv_minor' => 1200000,
            'depreciation_minor' => 16667,
            'closing_nbv_minor' => 1183333,
            'method_used' => 'straight_line',
        ];
    }
}
