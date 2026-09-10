<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Models\Detention;

/**
 * @extends Factory<Detention>
 */
class DetentionFactory extends Factory
{
    protected $model = Detention::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'scheduled_date' => now()->toDateString(),
            'starts_at' => '15:30:00',
            'ends_at' => '16:30:00',
            'status' => 'scheduled',
        ];
    }
}
