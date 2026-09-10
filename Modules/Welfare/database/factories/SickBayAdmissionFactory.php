<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Models\SickBayAdmission;

/**
 * @extends Factory<SickBayAdmission>
 */
class SickBayAdmissionFactory extends Factory
{
    protected $model = SickBayAdmission::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'admitted_at' => now(),
            'admitted_by' => User::factory(),
            'presenting_complaint' => 'Headache and nausea.',
            'severity' => 'minor',
            'excused_from_lessons' => true,
            'excused_from_activity' => true,
            'status' => 'admitted',
        ];
    }
}
