<?php

declare(strict_types=1);

namespace Modules\Operations\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Models\WorkOrder;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'term_id' => fn (array $attributes): int => Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $attributes['academic_year_id']])->id,
            'work_order_number' => 'WO-'.fake()->unique()->numberBetween(10000, 99999),
            'work_type' => 'corrective',
            'title' => 'Fix leaking tap',
            'description' => 'Replace washer and check for further leaks.',
            'priority' => 'normal',
            'assigned_team' => 'in_house',
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'status' => 'approved',
            'labour_hours' => 0,
            'labour_cost_minor' => 0,
            'parts_cost_minor' => 0,
            'contractor_cost_minor' => 0,
            'total_cost_minor' => 0,
            'currency' => 'USD',
            'raised_by' => User::factory(),
        ];
    }
}
