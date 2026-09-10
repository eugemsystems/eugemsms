<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Models\HealthScreening;

/**
 * @extends Factory<HealthScreening>
 */
class HealthScreeningFactory extends Factory
{
    protected $model = HealthScreening::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'screening_type' => 'vision',
            'screened_on' => now()->toDateString(),
            'outcome' => 'normal',
            'screened_by' => 'School Nurse',
        ];
    }
}
