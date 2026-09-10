<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ProjectRubric;
use Modules\Core\Models\School;

/**
 * @extends Factory<ProjectRubric>
 */
class ProjectRubricFactory extends Factory
{
    protected $model = ProjectRubric::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'SBP Generic Rubric',
            'subject_id' => null,
            'total_mark' => '100.00',
            'is_template' => true,
            'is_active' => true,
        ];
    }
}
