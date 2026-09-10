<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreRequisition;

/**
 * @extends Factory<StoreRequisition>
 */
class StoreRequisitionFactory extends Factory
{
    protected $model = StoreRequisition::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'requisition_number' => 'REQ/'.fake()->unique()->numberBetween(1000, 99999),
            'store_id' => Store::factory()->for($school),
            'cost_centre_id' => CostCentre::factory()->for($school),
            'purpose' => 'Kitchen provisions for the week.',
            'status' => 'draft',
            'requested_by' => User::factory(),
            'currency' => 'USD',
        ];
    }
}
