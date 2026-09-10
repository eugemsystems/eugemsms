<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ScheduledTask;
use Modules\Core\Models\ScheduledTaskRun;

/**
 * @extends Factory<ScheduledTaskRun>
 */
class ScheduledTaskRunFactory extends Factory
{
    protected $model = ScheduledTaskRun::class;

    public function definition(): array
    {
        return [
            'task_id' => ScheduledTask::factory(),
            'school_id' => null,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'duration_ms' => fake()->numberBetween(10, 5000),
            'output' => null,
            'error' => null,
        ];
    }
}
