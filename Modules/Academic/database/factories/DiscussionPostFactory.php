<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\DiscussionPost;
use Modules\Academic\Models\DiscussionThread;

/**
 * @extends Factory<DiscussionPost>
 */
class DiscussionPostFactory extends Factory
{
    protected $model = DiscussionPost::class;

    public function definition(): array
    {
        $thread = DiscussionThread::factory()->create();

        return [
            'school_id' => $thread->school_id,
            'thread_id' => $thread->id,
            'posted_by_type' => 'staff',
            'posted_by_id' => User::factory(),
            'content' => $this->faker->paragraph(),
            'is_hidden' => false,
            'posted_at' => now(),
        ];
    }
}
