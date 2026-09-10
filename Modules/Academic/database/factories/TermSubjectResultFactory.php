<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<TermSubjectResult>
 */
class TermSubjectResultFactory extends Factory
{
    protected $model = TermSubjectResult::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'subject_id' => Subject::factory()->for($school),
        ];
    }
}
