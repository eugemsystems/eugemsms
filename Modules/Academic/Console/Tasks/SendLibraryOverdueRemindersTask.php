<?php

declare(strict_types=1);

namespace Modules\Academic\Console\Tasks;

use Modules\Academic\Domain\Actions\SendOverdueReminderAction;
use Modules\Academic\Domain\DataObjects\SendOverdueReminderData;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;

/**
 * Scheduled (ACA-10 BR-ACA-10-004): reminds every borrower with an overdue loan, before any fine is charged. The notification bus dedupes per loan per day.
 */
final class SendLibraryOverdueRemindersTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $sent = 0;

        foreach (Loan::query()->where('status', 'active')->whereDate('due_on', '<', now()->toDateString())->pluck('id') as $loanId) {
            if (app(SendOverdueReminderAction::class)->execute(new SendOverdueReminderData((int) $loanId))) {
                $sent++;
            }
        }

        return "{$sent} reminder(s) sent";
    }
}
