<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectMilestone;

/**
 * @extends Factory<ProjectMilestone>
 */
class ProjectMilestoneFactory extends Factory
{
    protected $model = ProjectMilestone::class;

    public function definition(): array
    {
        $brief = ProjectBrief::factory();

        return [
            'school_id' => $brief,
            'brief_id' => $brief,
            'sequence' => 1,
            'title' => 'Research Plan',
            'description' => 'Submit a plan outlining the research approach.',
            'due_on' => now()->addWeeks(2)->toDateString(),
            'weight_percent' => '20.00',
            'requires_evidence' => true,
        ];
    }
}
