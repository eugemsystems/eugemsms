<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\GoodsReceivedNote;
use Modules\Stores\Models\GrnLine;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\PurchaseOrderLine;

/**
 * @extends Factory<GrnLine>
 */
class GrnLineFactory extends Factory
{
    protected $model = GrnLine::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'grn_id' => fn (array $attributes): int => GoodsReceivedNote::factory()->create(['school_id' => $attributes['school_id']])->id,
            'po_line_id' => fn (array $attributes): int => PurchaseOrderLine::factory()->create(['school_id' => $attributes['school_id']])->id,
            'item_id' => fn (array $attributes): int => InventoryItem::factory()->create(['school_id' => $attributes['school_id']])->id,
            'quantity_delivered' => 20,
            'quantity_accepted' => 20,
            'unit_cost_minor' => 5000,
        ];
    }
}
