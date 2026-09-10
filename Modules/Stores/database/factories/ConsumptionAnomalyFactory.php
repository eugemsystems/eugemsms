<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\ConsumptionAnomaly;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * @extends Factory<ConsumptionAnomaly>
 */
class ConsumptionAnomalyFactory extends Factory
{
    protected $model = ConsumptionAnomaly::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'store_id' => Store::factory()->for($school),
            'item_id' => InventoryItem::factory()->for($school),
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->toDateString(),
            'expected_quantity' => 100,
            'actual_quantity' => 130,
            'variance_percent' => 30,
            'severity' => 'high',
            'status' => 'flagged',
            'detected_at' => now(),
        ];
    }
}
