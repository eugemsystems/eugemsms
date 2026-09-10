<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockTransfer;
use Modules\Stores\Models\StockTransferLine;

/**
 * @extends Factory<StockTransferLine>
 */
class StockTransferLineFactory extends Factory
{
    protected $model = StockTransferLine::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'transfer_id' => StockTransfer::factory()->create(['school_id' => $school]),
            'item_id' => InventoryItem::factory()->for($school),
            'quantity_dispatched' => 20,
            'unit_cost_minor' => 80,
            'line_cost_minor' => 1600,
            'currency' => 'USD',
        ];
    }
}
