<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\PurchaseOrder;
use Modules\Stores\Models\PurchaseOrderLine;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'purchase_order_id' => fn (array $attributes): int => PurchaseOrder::factory()->create(['school_id' => $attributes['school_id']])->id,
            'line_number' => 1,
            'item_id' => fn (array $attributes): int => InventoryItem::factory()->create(['school_id' => $attributes['school_id']])->id,
            'description' => 'Photocopy paper, A4, 80gsm',
            'quantity_ordered' => 20,
            'unit' => 'ream',
            'unit_price_minor' => 5000,
            'tax_category' => 'standard',
            'line_total_minor' => 100000,
        ];
    }
}
