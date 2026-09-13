<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Concerns\RecordsScheduledTaskRun;
use Modules\Finance\Domain\Actions\CheckPaymentPlanBreachesAction;

/**
 * `php artisan serp:check-payment-plan-breaches` (Book B FIN-03 §4/
 * BR-FIN-03-017).
 */
final class CheckPaymentPlanBreachesCommand extends Command
{
    use RecordsScheduledTaskRun;

    protected $signature = 'serp:check-payment-plan-breaches';

    protected $description = 'Mark any active payment plan breached once an instalment is overdue past the grace period.';

    public function handle(CheckPaymentPlanBreachesAction $action): int
    {
        $this->recordScheduledTaskRun('finance.check_payment_plan_breaches', function () use ($action): string {
            $breached = $action->execute();
            $this->info("{$breached} plan(s) newly breached.");

            return "{$breached} plan(s) newly breached.";
        });

        return self::SUCCESS;
    }
}
