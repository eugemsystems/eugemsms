<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\AttendanceMarkingCompliance;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * @extends Factory<AttendanceMarkingCompliance>
 */
class AttendanceMarkingComplianceFactory extends Factory
{
    protected $model = AttendanceMarkingCompliance::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'staff_id' => Staff::factory()->for($school),
            'session_date' => now()->toDateString(),
        ];
    }
}
