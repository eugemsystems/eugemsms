<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\LearnerProjectMilestone;
use Modules\Academic\Models\ProjectMilestone;

/**
 * @extends Factory<LearnerProjectMilestone>
 */
class LearnerProjectMilestoneFactory extends Factory
{
    protected $model = LearnerProjectMilestone::class;

    public function definition(): array
    {
        $learnerProject = LearnerProject::factory();

        return [
            'school_id' => $learnerProject,
            'learner_project_id' => $learnerProject,
            'milestone_id' => ProjectMilestone::factory(),
            'status' => 'pending',
            'submitted_at' => null,
            'mark' => null,
            'feedback' => null,
            'reviewed_by' => null,
        ];
    }
}
