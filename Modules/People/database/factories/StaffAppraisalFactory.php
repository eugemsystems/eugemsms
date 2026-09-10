<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffAppraisal;

/**
 * @extends Factory<StaffAppraisal>
 */
class StaffAppraisalFactory extends Factory
{
    protected $model = StaffAppraisal::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school),
            'academic_year_id' => AcademicYear::factory()->for($school),
            'cycle' => 'annual',
            'appraiser_staff_id' => Staff::factory()->for($school),
            'status' => 'draft',
        ];
    }
}
