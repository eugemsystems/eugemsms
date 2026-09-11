<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\CourseSpace;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        $courseSpace = CourseSpace::factory()->create();

        return [
            'school_id' => $courseSpace->school_id,
            'course_space_id' => $courseSpace->id,
            'title' => $this->faker->sentence(4),
            'instructions' => $this->faker->paragraph(),
            'max_mark' => 100,
            'opens_at' => Carbon::now()->subDays(3),
            'due_at' => Carbon::now()->addDays(4),
            'late_policy' => 'block',
            'allows_resubmission' => false,
            'submission_type' => 'file',
            'status' => 'published',
        ];
    }
}
