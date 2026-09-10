<?php

declare(strict_types=1);

namespace Modules\Wallet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Wallet\Models\SpendPoint;
use Modules\Wallet\Models\WalletProduct;

/**
 * @extends Factory<WalletProduct>
 */
class WalletProductFactory extends Factory
{
    protected $model = WalletProduct::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'spend_point_id' => fn (array $attributes): int => SpendPoint::factory()->create(['school_id' => $attributes['school_id']])->id,
            'code' => 'PRD-'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Chocolate Bar',
            'category' => 'confectionery',
            'price_minor' => 100,
            'currency' => 'USD',
            'tax_type' => 'standard',
            'is_active' => true,
        ];
    }
}
