<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Department;
use Modules\Stores\Models\PurchaseRequisition;

/**
 * @extends Factory<PurchaseRequisition>
 */
class PurchaseRequisitionFactory extends Factory
{
    protected $model = PurchaseRequisition::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'term_id' => fn (array $attributes): int => Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $attributes['academic_year_id']])->id,
            'requisition_number' => 'PR/'.fake()->unique()->numberBetween(1000, 99999),
            'department_id' => fn (array $attributes): int => Department::factory()->create(['school_id' => $attributes['school_id']])->id,
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'justification' => 'Termly consumables restock.',
            'urgency' => 'normal',
            'estimated_total_minor' => 100000,
            'currency' => 'USD',
            'status' => 'draft',
            'requested_by' => User::factory(),
        ];
    }
}
