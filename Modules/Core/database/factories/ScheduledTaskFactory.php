<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ScheduledTask;

/**
 * @extends Factory<ScheduledTask>
 */
class ScheduledTaskFactory extends Factory
{
    protected $model = ScheduledTask::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'module_code' => 'core',
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'command' => 'app:noop',
            'schedule_expression' => '0 * * * *',
            'is_enabled' => true,
            'is_per_school' => false,
            'timeout_seconds' => 300,
            'alert_on_failure' => true,
            'alert_if_not_run_within_minutes' => null,
        ];
    }
}
