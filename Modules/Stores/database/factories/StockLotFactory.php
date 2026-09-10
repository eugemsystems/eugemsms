<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockLot;
use Modules\Stores\Models\Store;

/**
 * @extends Factory<StockLot>
 */
class StockLotFactory extends Factory
{
    protected $model = StockLot::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'store_id' => Store::factory()->for($school),
            'item_id' => InventoryItem::factory()->for($school),
            'lot_reference' => 'LOT-'.fake()->unique()->numberBetween(10000, 99999),
            'received_on' => now()->toDateString(),
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost_minor' => 80,
            'currency' => 'USD',
            'base_unit_cost_minor' => 80,
            'source_type' => 'opening',
            'is_depleted' => false,
        ];
    }
}
