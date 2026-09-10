<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\AssetMovement;
use Modules\Stores\Models\FixedAsset;

/**
 * @extends Factory<AssetMovement>
 */
class AssetMovementFactory extends Factory
{
    protected $model = AssetMovement::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'asset_id' => fn (array $attributes): int => FixedAsset::factory()->create(['school_id' => $attributes['school_id']])->id,
            'movement_type' => 'status_change',
            'performed_by' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
