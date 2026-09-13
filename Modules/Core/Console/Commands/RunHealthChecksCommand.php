<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Concerns\RecordsScheduledTaskRun;
use Modules\Core\Domain\Actions\Scheduling\RunHealthChecksAction;
use Modules\Core\Domain\DataObjects\Scheduling\RunHealthChecksData;
use Modules\Core\Models\SystemHealthCheck;

/**
 * `php artisan serp:run-health-checks` (Book A CORE-12 §3). Scheduled
 * every 5 minutes in `routes/console.php` — the cadence the "Scheduler
 * last run"/"< 2 min healthy" style thresholds elsewhere in this suite
 * assume a check this frequent, not once a day.
 */
final class RunHealthChecksCommand extends Command
{
    use RecordsScheduledTaskRun;

    protected $signature = 'serp:run-health-checks';

    protected $description = 'Run every registered, available system health check.';

    public function handle(RunHealthChecksAction $action): int
    {
        $this->recordScheduledTaskRun('core.run_health_checks', function () use ($action): string {
            $results = $action->execute(new RunHealthChecksData);
            $unhealthy = collect($results)->filter(fn (SystemHealthCheck $check): bool => $check->status === 'unhealthy')->count();

            $this->info("{$unhealthy} of ".count($results).' check(s) unhealthy.');

            return count($results).' check(s) run, '.$unhealthy.' unhealthy.';
        });

        return self::SUCCESS;
    }
}
