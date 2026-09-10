<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockTake;
use Modules\Stores\Models\StockTakeLine;

/**
 * @extends Factory<StockTakeLine>
 */
class StockTakeLineFactory extends Factory
{
    protected $model = StockTakeLine::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'stock_take_id' => StockTake::factory()->create(['school_id' => $school]),
            'item_id' => InventoryItem::factory()->for($school),
            'system_quantity' => 100,
            'requires_recount' => false,
        ];
    }
}
