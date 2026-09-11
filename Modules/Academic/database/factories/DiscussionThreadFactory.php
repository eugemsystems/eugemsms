<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\DiscussionThread;

/**
 * @extends Factory<DiscussionThread>
 */
class DiscussionThreadFactory extends Factory
{
    protected $model = DiscussionThread::class;

    public function definition(): array
    {
        $courseSpace = CourseSpace::factory()->create();

        return [
            'school_id' => $courseSpace->school_id,
            'course_space_id' => $courseSpace->id,
            'title' => $this->faker->sentence(3),
            'created_by' => User::factory(),
            'is_locked' => false,
            'is_pinned' => false,
        ];
    }
}
