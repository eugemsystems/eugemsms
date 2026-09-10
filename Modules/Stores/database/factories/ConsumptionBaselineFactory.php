<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\ConsumptionBaseline;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * @extends Factory<ConsumptionBaseline>
 */
class ConsumptionBaselineFactory extends Factory
{
    protected $model = ConsumptionBaseline::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'store_id' => Store::factory()->for($school),
            'item_id' => InventoryItem::factory()->for($school),
            'period_type' => 'per_boarder_day',
            'expected_quantity' => 0.15,
            'tolerance_percent' => 15,
            'computed_from_days' => 60,
        ];
    }
}
