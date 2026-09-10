<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\PurchaseRequisition;
use Modules\Stores\Models\PurchaseRequisitionLine;

/**
 * @extends Factory<PurchaseRequisitionLine>
 */
class PurchaseRequisitionLineFactory extends Factory
{
    protected $model = PurchaseRequisitionLine::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'requisition_id' => fn (array $attributes): int => PurchaseRequisition::factory()->create(['school_id' => $attributes['school_id']])->id,
            'item_id' => fn (array $attributes): int => InventoryItem::factory()->create(['school_id' => $attributes['school_id']])->id,
            'description' => 'Photocopy paper, A4, 80gsm',
            'quantity' => 20,
            'unit' => 'ream',
            'estimated_unit_minor' => 500,
            'estimated_total_minor' => 10000,
            'currency' => 'USD',
        ];
    }
}
