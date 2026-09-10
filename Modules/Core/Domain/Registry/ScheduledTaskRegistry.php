<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Domain\DataObjects\Scheduling\ScheduledTaskDefinitionData;
use Modules\Core\Models\ScheduledTask;

/**
 * Book A CORE-12 §2. Code owns the list; `syncToDatabase()` mirrors it
 * into `scheduled_tasks` — same split as `ImporterRegistry`.
 */
final class ScheduledTaskRegistry
{
    /**
     * @var array<string, ScheduledTaskDefinitionData>
     */
    private static array $tasks = [];

    public static function register(ScheduledTaskDefinitionData $task): void
    {
        self::$tasks[$task->key] = $task;
    }

    public static function get(string $key): ?ScheduledTaskDefinitionData
    {
        return self::$tasks[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$tasks);
    }

    /**
     * @return array<string, ScheduledTaskDefinitionData>
     */
    public static function all(): array
    {
        return self::$tasks;
    }

    public static function clear(): void
    {
        self::$tasks = [];
    }

    public static function syncToDatabase(): void
    {
        $keys = [];

        foreach (self::$tasks as $key => $task) {
            $keys[] = $key;

            ScheduledTask::updateOrCreate(['key' => $key], [
                'module_code' => $task->moduleCode,
                'name' => $task->name,
                'description' => $task->description,
                'command' => $task->command,
                'schedule_expression' => $task->scheduleExpression,
                'is_enabled' => $task->isEnabled,
                'is_per_school' => $task->isPerSchool,
                'timeout_seconds' => $task->timeoutSeconds,
                'alert_on_failure' => $task->alertOnFailure,
                'alert_if_not_run_within_minutes' => $task->alertIfNotRunWithinMinutes,
            ]);
        }

        ScheduledTask::query()->whereNotIn('key', $keys)->delete();
    }
}
