<?php

declare(strict_types=1);

namespace Modules\Wallet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Wallet\Models\WalletProduct;
use Modules\Wallet\Models\WalletSale;
use Modules\Wallet\Models\WalletSaleLine;

/**
 * @extends Factory<WalletSaleLine>
 */
class WalletSaleLineFactory extends Factory
{
    protected $model = WalletSaleLine::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'sale_id' => fn (array $attributes): int => WalletSale::factory()->create(['school_id' => $attributes['school_id']])->id,
            'product_id' => fn (array $attributes): int => WalletProduct::factory()->create(['school_id' => $attributes['school_id']])->id,
            'quantity' => 1,
            'unit_price_minor' => 100,
            'line_total_minor' => 100,
            'tax_type' => 'standard',
        ];
    }
}
