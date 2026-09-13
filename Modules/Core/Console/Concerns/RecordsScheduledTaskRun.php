<?php

declare(strict_types=1);

namespace Modules\Core\Console\Concerns;

use Modules\Core\Domain\Actions\Scheduling\CompleteScheduledTaskRunAction;
use Modules\Core\Domain\Actions\Scheduling\StartScheduledTaskRunAction;
use Modules\Core\Domain\DataObjects\Scheduling\CompleteScheduledTaskRunData;
use Modules\Core\Domain\DataObjects\Scheduling\StartScheduledTaskRunData;
use Throwable;

/**
 * Shared plumbing for every scheduled console command (Book A CORE-12
 * §2/BR-CORE-12-007) — records a `scheduled_task_runs` row around the
 * command's own work so `Scheduling\Tasks`/`TaskRuns` and
 * `SchedulerLastRunHealthCheck`/`CheckScheduledTaskFreshnessAction` have
 * something real to read, instead of a registry entry nothing ever
 * actually runs. `$taskKey` must match a key `ScheduledTaskRegistry`
 * has registered (and synced to `scheduled_tasks`) — see each command's
 * own registration in `CoreServiceProvider::registerScheduledTasks()`.
 */
trait RecordsScheduledTaskRun
{
    /**
     * @param  callable(): (string|null)  $work  runs the task's real work,
     *                                           returning an optional short output string for the run's
     *                                           own record; throwing marks the run failed and re-throws
     *                                           so the exit code still reflects failure to cron/CI.
     */
    protected function recordScheduledTaskRun(string $taskKey, callable $work, ?int $schoolId = null): void
    {
        $run = app(StartScheduledTaskRunAction::class)->execute(new StartScheduledTaskRunData($taskKey, $schoolId));

        try {
            $output = $work();

            app(CompleteScheduledTaskRunAction::class)->execute(new CompleteScheduledTaskRunData(
                runId: $run->id,
                status: 'completed',
                output: $output,
            ));
        } catch (Throwable $e) {
            app(CompleteScheduledTaskRunAction::class)->execute(new CompleteScheduledTaskRunData(
                runId: $run->id,
                status: 'failed',
                error: $e->getMessage(),
            ));

            throw $e;
        }
    }
}
