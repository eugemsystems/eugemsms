<?php

declare(strict_types=1);

namespace Modules\Comms\Console\Tasks;

use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Actions\RunScanRuleAction;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\ScanRun;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;

/**
 * Scheduled (COM-04): runs every active scheduled-scan automation rule whose cron expression has come due since its last run.
 */
final class RunDueScanRulesTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $ran = 0;

        foreach (AutomationRule::query()->where('is_active', true)->where('trigger_type', 'scheduled_scan')->whereNotNull('schedule_cron')->get() as $rule) {
            if (! CronExpression::isValidExpression((string) $rule->schedule_cron)) {
                continue;
            }

            $previousDue = Carbon::instance((new CronExpression((string) $rule->schedule_cron))->getPreviousRunDate(now()));
            $lastRan = ScanRun::query()->where('rule_id', $rule->id)->max('ran_at');

            if ($lastRan !== null && Carbon::parse($lastRan)->gte($previousDue)) {
                continue;
            }

            app(RunScanRuleAction::class)->execute($rule->id);
            $ran++;
        }

        return "{$ran} scan rule(s) run";
    }
}
