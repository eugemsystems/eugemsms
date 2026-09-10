<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Models\HealthIncident;

/**
 * @extends Factory<HealthIncident>
 */
class HealthIncidentFactory extends Factory
{
    protected $model = HealthIncident::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'incident_type' => 'fall',
            'occurred_at' => now(),
            'location' => 'Playground',
            'description' => 'Tripped and grazed knee.',
            'severity' => 'minor',
            'reported_by' => User::factory(),
        ];
    }
}
