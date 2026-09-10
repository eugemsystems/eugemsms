<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockBalance;
use Modules\Stores\Models\Store;

/**
 * @extends Factory<StockBalance>
 */
class StockBalanceFactory extends Factory
{
    protected $model = StockBalance::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'store_id' => Store::factory()->for($school),
            'item_id' => InventoryItem::factory()->for($school),
            'currency' => 'USD',
        ];
    }
}
