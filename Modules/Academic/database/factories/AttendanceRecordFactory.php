<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);
        $term = Term::factory()->for($school)->for($year, 'academicYear');

        return [
            'school_id' => $school,
            'session_id' => AttendanceSession::factory()->for($school)->state(['term_id' => $term, 'academic_year_id' => $year]),
            'student_id' => Student::factory()->for($school),
            'term_id' => $term,
            'session_date' => now()->toDateString(),
            'status' => 'present',
            'marked_at' => now(),
        ];
    }
}
