<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectPrerequisite;
use Modules\Core\Models\School;

/**
 * @extends Factory<SubjectPrerequisite>
 */
class SubjectPrerequisiteFactory extends Factory
{
    protected $model = SubjectPrerequisite::class;

    public function definition(): array
    {
        $school = School::factory();
        $framework = CurriculumFramework::factory()->for($school);

        return [
            'school_id' => $school,
            'subject_id' => Subject::factory()->for($school)->state(['framework_id' => $framework]),
            'prerequisite_subject_id' => Subject::factory()->for($school)->state(['framework_id' => $framework]),
            'severity' => 'warn',
        ];
    }
}
