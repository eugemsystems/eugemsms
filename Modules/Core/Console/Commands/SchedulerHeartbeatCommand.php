<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Concerns\RecordsScheduledTaskRun;
use Modules\Core\Domain\Support\Scheduling\SchedulerLastRunHealthCheck;

/**
 * `php artisan core:scheduler-heartbeat` (Book A CORE-12 §3/
 * AC-CORE-12-003). Does nothing but record that it ran — the entire
 * point is proof of life for `SchedulerLastRunHealthCheck`, scheduled
 * every minute in `routes/console.php`.
 */
final class SchedulerHeartbeatCommand extends Command
{
    use RecordsScheduledTaskRun;

    protected $signature = 'core:scheduler-heartbeat';

    protected $description = 'Record a heartbeat proving the scheduler is running (health check only).';

    public function handle(): int
    {
        $this->recordScheduledTaskRun(SchedulerLastRunHealthCheck::HEARTBEAT_TASK_KEY, fn (): string => 'ok');

        return self::SUCCESS;
    }
}
