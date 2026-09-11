<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * @extends Factory<SchemeOfWork>
 */
class SchemeOfWorkFactory extends Factory
{
    protected $model = SchemeOfWork::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);
        $framework = CurriculumFramework::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'subject_id' => Subject::factory()->for($school)->state(fn (): array => ['framework_id' => $framework]),
            'grade_level_id' => GradeLevel::factory()->for($school),
            'teacher_staff_id' => Staff::factory()->for($school),
            'planned_topics' => [
                ['week' => 1, 'topic' => 'Introduction', 'objectives' => 'Understand basics', 'resources' => 'Textbook ch.1'],
                ['week' => 2, 'topic' => 'Core concepts', 'objectives' => 'Apply core concepts', 'resources' => 'Textbook ch.2'],
            ],
            'status' => 'draft',
        ];
    }
}
