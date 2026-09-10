<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Syllabus;
use Modules\Core\Models\School;

/**
 * @extends Factory<Syllabus>
 */
class SyllabusFactory extends Factory
{
    protected $model = Syllabus::class;

    public function definition(): array
    {
        $school = School::factory();
        $framework = CurriculumFramework::factory()->for($school);

        return [
            'school_id' => $school,
            'subject_id' => Subject::factory()->for($school)->state(['framework_id' => $framework]),
            'framework_id' => $framework,
            'title' => 'Syllabus',
            'is_active' => true,
        ];
    }
}
