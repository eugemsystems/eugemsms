<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\JobProgress;
use Modules\Core\Models\School;

/**
 * @extends Factory<JobProgress>
 */
class JobProgressFactory extends Factory
{
    protected $model = JobProgress::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'job_type' => 'bulk_export',
            'title' => fake()->sentence(3),
            'status' => 'queued',
            'total_steps' => null,
            'completed_steps' => 0,
            'current_message' => null,
            'result' => null,
            'error' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
