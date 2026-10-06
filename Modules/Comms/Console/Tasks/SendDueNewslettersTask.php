<?php

declare(strict_types=1);

namespace Modules\Comms\Console\Tasks;

use Modules\Comms\Domain\Actions\SendNewsletterAction;
use Modules\Comms\Models\Newsletter;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;
use Throwable;

/**
 * Scheduled (COM-06): sends every newsletter whose scheduled time has passed.
 */
final class SendDueNewslettersTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $sent = 0;

        foreach (Newsletter::query()->where('status', 'scheduled')->where('scheduled_for', '<=', now())->pluck('id') as $newsletterId) {
            try {
                app(SendNewsletterAction::class)->execute((int) $newsletterId);
                $sent++;
            } catch (Throwable) {
                // A refused issue (unsupported audience) stays scheduled for a person to fix.
            }
        }

        return "{$sent} newsletter(s) sent";
    }
}
