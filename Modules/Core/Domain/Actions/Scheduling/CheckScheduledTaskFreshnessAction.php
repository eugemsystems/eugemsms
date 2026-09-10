<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Scheduling;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordSecurityEventAction;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Models\ScheduledTask;

/**
 * ACT-CheckScheduledTaskFreshness (Book A CORE-12 §4/BR-CORE-12-007/
 * AC-CORE-12-003). "A silent scheduler is a worse failure than a loud
 * one" — every enabled task carrying `alert_if_not_run_within_minutes`
 * is checked against its own most recent run (any status; a task that
 * ran and failed is not silent). A task that has never run at all is
 * always stale.
 */
final class CheckScheduledTaskFreshnessAction extends Action
{
    public function __construct(
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    /**
     * @return array<int, ScheduledTask>
     */
    public function execute(): array
    {
        $stale = [];

        $tasks = ScheduledTask::query()
            ->where('is_enabled', true)
            ->whereNotNull('alert_if_not_run_within_minutes')
            ->get();

        foreach ($tasks as $task) {
            $lastRunAt = $task->runs()->max('started_at');

            $minutesSinceLastRun = $lastRunAt === null
                ? null
                : (int) floor(now()->diffInSeconds($lastRunAt, absolute: true) / 60);

            $isStale = $minutesSinceLastRun === null || $minutesSinceLastRun > $task->alert_if_not_run_within_minutes;

            if (! $isStale) {
                continue;
            }

            $stale[] = $task;

            $this->recordSecurityEvent->execute(new RecordSecurityEventData(
                eventType: 'scheduled_task_stale',
                severity: 'critical',
                description: $lastRunAt === null
                    ? "Scheduled task [{$task->key}] has never run."
                    : "Scheduled task [{$task->key}] has not run in {$minutesSinceLastRun} minutes (limit {$task->alert_if_not_run_within_minutes}).",
                context: ['task_key' => $task->key, 'minutes_since_last_run' => $minutesSinceLastRun],
            ));
        }

        return $stale;
    }
}
