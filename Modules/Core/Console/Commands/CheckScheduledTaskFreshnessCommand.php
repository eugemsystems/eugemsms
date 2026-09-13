<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Concerns\RecordsScheduledTaskRun;
use Modules\Core\Domain\Actions\Scheduling\CheckScheduledTaskFreshnessAction;

/**
 * `php artisan serp:check-scheduled-task-freshness` (Book A CORE-12
 * §4/BR-CORE-12-007/AC-CORE-12-003). Scheduled hourly — checking every
 * task's own freshness is itself a task, recorded like any other so a
 * broken freshness check is as visible as a broken backup.
 */
final class CheckScheduledTaskFreshnessCommand extends Command
{
    use RecordsScheduledTaskRun;

    protected $signature = 'serp:check-scheduled-task-freshness';

    protected $description = 'Alert on any enabled scheduled task that has not run within its expected window.';

    public function handle(CheckScheduledTaskFreshnessAction $action): int
    {
        $this->recordScheduledTaskRun('core.check_scheduled_task_freshness', function () use ($action): string {
            $stale = $action->execute();

            $this->info(count($stale).' stale task(s) found.');

            return count($stale).' stale task(s): '.collect($stale)->pluck('key')->implode(', ');
        });

        return self::SUCCESS;
    }
}
