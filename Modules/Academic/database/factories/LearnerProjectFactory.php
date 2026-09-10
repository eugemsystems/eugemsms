<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<LearnerProject>
 */
class LearnerProjectFactory extends Factory
{
    protected $model = LearnerProject::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'brief_id' => ProjectBrief::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'subject_id' => Subject::factory()->for($school),
            'project_title' => null,
            'status' => 'assigned',
            'raw_mark' => null,
            'percent' => null,
            'grade' => null,
            'outcome' => null,
            'criterion_marks' => null,
            'marker_staff_id' => null,
            'marked_at' => null,
            'marker_comment' => null,
            'moderator_staff_id' => null,
            'moderated_at' => null,
            'moderated_mark' => null,
            'moderation_note' => null,
            'verified_by' => null,
            'verified_at' => null,
            'exemption_reason' => null,
            'version' => 1,
            'submitted_at' => null,
        ];
    }
}
