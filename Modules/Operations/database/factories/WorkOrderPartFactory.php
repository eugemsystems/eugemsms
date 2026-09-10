<?php

declare(strict_types=1);

namespace Modules\Operations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Operations\Models\WorkOrder;
use Modules\Operations\Models\WorkOrderPart;

/**
 * @extends Factory<WorkOrderPart>
 */
class WorkOrderPartFactory extends Factory
{
    protected $model = WorkOrderPart::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'work_order_id' => fn (array $attributes): int => WorkOrder::factory()->create(['school_id' => $attributes['school_id']])->id,
            'description' => 'Tap washer',
            'quantity' => 2,
            'unit' => 'ea',
            'source' => 'store',
        ];
    }
}
