<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\AssetVerification;
use Modules\Stores\Models\FixedAsset;

/**
 * @extends Factory<AssetVerification>
 */
class AssetVerificationFactory extends Factory
{
    protected $model = AssetVerification::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'verification_round' => 'ROUND-'.fake()->unique()->numberBetween(1000, 9999),
            'asset_id' => fn (array $attributes): int => FixedAsset::factory()->create(['school_id' => $attributes['school_id']])->id,
            'status' => 'pending',
        ];
    }
}
