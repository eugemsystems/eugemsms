<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<MissingLearnerIncident>
 */
class MissingLearnerIncidentFactory extends Factory
{
    protected $model = MissingLearnerIncident::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);
        $term = Term::factory()->for($school)->for($year, 'academicYear');

        return [
            'school_id' => $school,
            'term_id' => $term,
            'student_id' => Student::factory()->for($school),
            'roll_call_id' => RollCall::factory()->state([
                'school_id' => $school,
                'term_id' => $term,
                'roll_call_point_id' => RollCallPoint::factory()->for($school),
                'hostel_id' => Hostel::factory()->for($school),
            ]),
            'first_missed_at' => now(),
            'current_step' => 1,
            'status' => 'open',
        ];
    }
}
