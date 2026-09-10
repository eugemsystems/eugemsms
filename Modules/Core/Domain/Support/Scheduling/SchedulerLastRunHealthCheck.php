<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Scheduling;

use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;
use Modules\Core\Models\ScheduledTaskRun;

/**
 * Book A CORE-12 §3/AC-CORE-12-003. Tracks the `core.scheduler_heartbeat`
 * task (registered every minute in `CoreServiceProvider`) rather than
 * "any task ran recently" — most real tasks run hourly or daily, which
 * would make this check permanently, falsely unhealthy.
 */
final class SchedulerLastRunHealthCheck implements HealthCheck
{
    public const HEARTBEAT_TASK_KEY = 'core.scheduler_heartbeat';

    public function checkKey(): string
    {
        return 'scheduler_last_run';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $lastRun = ScheduledTaskRun::query()
            ->whereHas('task', fn ($q) => $q->where('key', self::HEARTBEAT_TASK_KEY))
            ->max('started_at');

        if ($lastRun === null) {
            return new HealthCheckResult(status: 'unhealthy', message: 'The scheduler heartbeat has never run.');
        }

        $ageMinutes = (int) floor(now()->diffInSeconds($lastRun, absolute: true) / 60);

        $status = match (true) {
            $ageMinutes > 10 => 'unhealthy',
            $ageMinutes >= 2 => 'degraded',
            default => 'healthy',
        };

        return new HealthCheckResult(
            status: $status,
            value: "{$ageMinutes} min ago",
            threshold: '< 2 min healthy, 2-10 min degraded, > 10 min unhealthy',
        );
    }
}
