<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Closure;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Domain\DataObjects\Scheduling\ScheduledTaskDefinitionData;
use Modules\Core\Models\School;

/**
 * Registers a per-school scheduled job in one call: the CORE-12 task
 * definition (so it shows in `Scheduling\Tasks`, is watched for freshness and
 * is scheduled by `routes/console.php`) plus the handler `serp:run-task`
 * resolves for it. Modules call `register()` from their provider's boot.
 */
final class ScheduledTaskHandlerRegistry
{
    /**
     * @var array<string, Closure(School): string|class-string<ScheduledTaskHandler>>
     */
    private static array $handlers = [];

    /**
     * @var array<string, Closure(): string>
     */
    private static array $globalHandlers = [];

    /**
     * @param  Closure(School): string|class-string<ScheduledTaskHandler>  $handler  one school's work; returns a short summary
     */
    public static function register(string $key, string $moduleCode, string $name, string $cron, Closure|string $handler, ?string $description = null, ?int $alertIfNotRunWithinMinutes = null, int $timeoutSeconds = 600): void
    {
        self::$handlers[$key] = $handler;

        ScheduledTaskRegistry::register(new ScheduledTaskDefinitionData(
            key: $key,
            moduleCode: $moduleCode,
            name: $name,
            command: "serp:run-task {$key}",
            scheduleExpression: $cron,
            description: $description,
            isPerSchool: true,
            timeoutSeconds: $timeoutSeconds,
            alertIfNotRunWithinMinutes: $alertIfNotRunWithinMinutes,
        ));
    }

    /**
     * Registers a platform-level job (tenants, subscriptions, vendor support)
     * that runs once, not once per school.
     *
     * @param  Closure(): string  $handler
     */
    public static function registerGlobal(string $key, string $moduleCode, string $name, string $cron, Closure $handler, ?string $description = null, ?int $alertIfNotRunWithinMinutes = null, int $timeoutSeconds = 600): void
    {
        self::$globalHandlers[$key] = $handler;

        ScheduledTaskRegistry::register(new ScheduledTaskDefinitionData(
            key: $key,
            moduleCode: $moduleCode,
            name: $name,
            command: "serp:run-task {$key}",
            scheduleExpression: $cron,
            description: $description,
            isPerSchool: false,
            timeoutSeconds: $timeoutSeconds,
            alertIfNotRunWithinMinutes: $alertIfNotRunWithinMinutes,
        ));
    }

    /**
     * @return Closure(): string|null
     */
    public static function globalHandlerFor(string $key): ?Closure
    {
        return self::$globalHandlers[$key] ?? null;
    }

    /**
     * @return Closure(School): string|class-string<ScheduledTaskHandler>|null
     */
    public static function handlerFor(string $key): Closure|string|null
    {
        return self::$handlers[$key] ?? null;
    }

    /**
     * @return array<string, Closure(School): string|class-string<ScheduledTaskHandler>>
     */
    public static function all(): array
    {
        return self::$handlers;
    }
}
