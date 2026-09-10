<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LevelSubjectOffering;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;

/**
 * @extends Factory<LevelSubjectOffering>
 */
class LevelSubjectOfferingFactory extends Factory
{
    protected $model = LevelSubjectOffering::class;

    public function definition(): array
    {
        $school = School::factory();
        $framework = CurriculumFramework::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'grade_level_id' => GradeLevel::factory()->for($school),
            'subject_id' => Subject::factory()->for($school)->state(['framework_id' => $framework]),
            'is_compulsory' => false,
            'is_available' => true,
        ];
    }
}
