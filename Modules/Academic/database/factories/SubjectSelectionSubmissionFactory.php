<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\SubjectSelectionSubmission;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<SubjectSelectionSubmission>
 */
class SubjectSelectionSubmissionFactory extends Factory
{
    protected $model = SubjectSelectionSubmission::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'grade_level_id' => GradeLevel::factory()->for($school),
            'selected_subject_ids' => [],
            'status' => 'submitted',
        ];
    }
}
