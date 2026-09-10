<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectRubric;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;

/**
 * @extends Factory<ProjectBrief>
 */
class ProjectBriefFactory extends Factory
{
    protected $model = ProjectBrief::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'instrument_id' => AssessmentInstrument::factory()->for($school),
            'subject_id' => Subject::factory()->for($school),
            'grade_level_id' => GradeLevel::factory(),
            'title' => 'Improving Water Access in Our Community',
            'description' => 'A school-based project investigating a local heritage-linked problem.',
            'learning_objectives' => ['Apply research skills', 'Demonstrate practical problem solving'],
            'heritage_link' => 'Heritage-based education: water conservation',
            'deliverables' => ['Written report', 'Practical artefact', 'Oral presentation'],
            'resources' => ['Library access', 'Science lab'],
            'starts_on' => now()->toDateString(),
            'due_on' => now()->addWeeks(8)->toDateString(),
            'max_mark' => '100.00',
            'rubric_id' => ProjectRubric::factory()->for($school),
            'brief_document_id' => null,
            'status' => 'draft',
            'approved_by' => null,
            'issued_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
