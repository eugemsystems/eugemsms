<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<AssessmentMark>
 */
class AssessmentMarkFactory extends Factory
{
    protected $model = AssessmentMark::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);
        $term = Term::factory()->for($school)->for($year, 'academicYear');

        return [
            'school_id' => $school,
            'assessment_id' => Assessment::factory()->for($school)->state(['academic_year_id' => $year, 'term_id' => $term]),
            'student_id' => Student::factory()->for($school),
            'term_id' => $term,
            'raw_mark' => 75,
            'percent' => 75,
            'version' => 1,
            'entered_by' => User::factory(),
            'entered_at' => now(),
        ];
    }
}
