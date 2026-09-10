<?php

declare(strict_types=1);

namespace Modules\Operations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Operations\Models\CapitalProject;
use Modules\Operations\Models\CapitalProjectMilestone;

/**
 * @extends Factory<CapitalProjectMilestone>
 */
class CapitalProjectMilestoneFactory extends Factory
{
    protected $model = CapitalProjectMilestone::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'project_id' => fn (array $attributes): int => CapitalProject::factory()->create(['school_id' => $attributes['school_id']])->id,
            'sequence' => 1,
            'name' => 'Foundation complete',
            'target_date' => now()->addMonths(2)->toDateString(),
            'status' => 'pending',
        ];
    }
}
