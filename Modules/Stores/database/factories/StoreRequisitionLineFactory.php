<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StoreRequisition;
use Modules\Stores\Models\StoreRequisitionLine;

/**
 * @extends Factory<StoreRequisitionLine>
 */
class StoreRequisitionLineFactory extends Factory
{
    protected $model = StoreRequisitionLine::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'requisition_id' => StoreRequisition::factory()->for($school),
            'item_id' => InventoryItem::factory()->for($school),
            'quantity_requested' => 10,
            'unit' => 'kg',
        ];
    }
}
