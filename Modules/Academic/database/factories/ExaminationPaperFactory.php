<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;

/**
 * @extends Factory<ExaminationPaper>
 */
class ExaminationPaperFactory extends Factory
{
    protected $model = ExaminationPaper::class;

    public function definition(): array
    {
        $school = School::factory();
        $framework = CurriculumFramework::factory()->for($school);

        return [
            'school_id' => $school,
            'session_id' => ExaminationSession::factory()->for($school),
            'subject_id' => Subject::factory()->for($school)->state(['framework_id' => $framework]),
            'grade_level_id' => GradeLevel::factory()->for($school),
            'paper_number' => '1',
            'paper_name' => 'Paper 1: Theory',
            'component_type' => 'theory',
            'max_mark' => '100.00',
            'weight_percent' => '100.00',
            'duration_minutes' => 120,
            'scheduled_date' => now()->addWeeks(4)->toDateString(),
            'status' => 'draft',
        ];
    }
}
