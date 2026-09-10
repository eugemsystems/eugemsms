<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\AssetDisposal;
use Modules\Stores\Models\FixedAsset;

/**
 * @extends Factory<AssetDisposal>
 */
class AssetDisposalFactory extends Factory
{
    protected $model = AssetDisposal::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'asset_id' => fn (array $attributes): int => FixedAsset::factory()->create(['school_id' => $attributes['school_id']])->id,
            'disposal_date' => now()->toDateString(),
            'disposal_method' => 'sale',
            'proceeds_minor' => 0,
            'currency' => 'USD',
            'nbv_at_disposal_minor' => 0,
            'gain_loss_minor' => 0,
            'reason' => 'End of useful life.',
        ];
    }
}
