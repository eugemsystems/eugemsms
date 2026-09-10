<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\AttendanceSummary;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<AttendanceSummary>
 */
class AttendanceSummaryFactory extends Factory
{
    protected $model = AttendanceSummary::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'scope' => 'term',
        ];
    }
}
