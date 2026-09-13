<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Concerns\RecordsScheduledTaskRun;
use Modules\Finance\Domain\Actions\SendDueRemindersAction;

/**
 * `php artisan serp:send-fee-reminders` (Book B FIN-03 §4/BR-FIN-03-015).
 */
final class SendFeeRemindersCommand extends Command
{
    use RecordsScheduledTaskRun;

    protected $signature = 'serp:send-fee-reminders';

    protected $description = 'Send every reminder-ladder rung due to fire across every school.';

    public function handle(SendDueRemindersAction $action): int
    {
        $this->recordScheduledTaskRun('finance.send_fee_reminders', function () use ($action): string {
            $sent = $action->execute();
            $this->info("{$sent} reminder(s) sent.");

            return "{$sent} reminder(s) sent.";
        });

        return self::SUCCESS;
    }
}
