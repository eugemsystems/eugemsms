<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectAmendmentRequest;
use Modules\Core\Models\School;

/**
 * @extends Factory<ProjectAmendmentRequest>
 */
class ProjectAmendmentRequestFactory extends Factory
{
    protected $model = ProjectAmendmentRequest::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'learner_project_id' => LearnerProject::factory(),
            'new_criterion_marks' => ['content' => 18, 'presentation' => 9],
            'change_reason' => 'Re-marked after a moderation review found a tallying error.',
            'requested_by' => User::factory(),
            'status' => 'pending',
        ];
    }
}
