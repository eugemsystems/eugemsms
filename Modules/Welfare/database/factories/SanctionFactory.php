<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Models\Sanction;
use Modules\Welfare\Models\SanctionType;

/**
 * @extends Factory<Sanction>
 */
class SanctionFactory extends Factory
{
    protected $model = Sanction::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'sanction_type_id' => SanctionType::factory()->create(['school_id' => $school]),
            'behaviour_record_ids' => [],
            'reason' => 'Repeated demerits over the term.',
            'starts_on' => now()->toDateString(),
            'status' => 'proposed',
            'issued_by' => User::factory(),
        ];
    }
}
