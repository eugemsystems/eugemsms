<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentType;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'assessment_type_id' => AssessmentType::factory()->for($school),
            'subject_id' => Subject::factory()->for($school),
            'title' => 'Topic Test 1',
            'max_mark' => 100,
            'weight_percent' => 10,
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
