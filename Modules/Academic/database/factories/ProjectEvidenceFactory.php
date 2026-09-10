<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectEvidence;

/**
 * @extends Factory<ProjectEvidence>
 */
class ProjectEvidenceFactory extends Factory
{
    protected $model = ProjectEvidence::class;

    public function definition(): array
    {
        $learnerProject = LearnerProject::factory();

        return [
            'school_id' => $learnerProject,
            'learner_project_id' => $learnerProject,
            'milestone_id' => null,
            'evidence_type' => 'document',
            'file_id' => null,
            'external_url' => null,
            'caption' => 'Draft research plan',
            'uploaded_by' => User::factory(),
            'uploaded_at' => now(),
            'is_final_submission' => false,
        ];
    }
}
