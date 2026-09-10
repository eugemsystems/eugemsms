<?php

declare(strict_types=1);

namespace Modules\Operations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Operations\Models\WorkOrder;
use Modules\Operations\Models\WorkOrderLabour;
use Modules\People\Models\Staff;

/**
 * @extends Factory<WorkOrderLabour>
 */
class WorkOrderLabourFactory extends Factory
{
    protected $model = WorkOrderLabour::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'work_order_id' => fn (array $attributes): int => WorkOrder::factory()->create(['school_id' => $attributes['school_id']])->id,
            'staff_id' => fn (array $attributes): int => Staff::factory()->create(['school_id' => $attributes['school_id']])->id,
            'work_date' => now()->toDateString(),
            'hours' => 2,
        ];
    }
}
