<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectMarkVersion;

/**
 * @extends Factory<ProjectMarkVersion>
 */
class ProjectMarkVersionFactory extends Factory
{
    protected $model = ProjectMarkVersion::class;

    public function definition(): array
    {
        $learnerProject = LearnerProject::factory();

        return [
            'school_id' => $learnerProject,
            'learner_project_id' => $learnerProject,
            'version' => 1,
            'raw_mark' => '78.00',
            'criterion_marks' => ['Research & Planning' => 20],
            'stage' => 'marked',
            'change_reason' => null,
            'changed_by' => User::factory(),
            'changed_at' => now(),
        ];
    }
}
