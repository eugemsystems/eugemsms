<?php

declare(strict_types=1);

namespace Modules\Comms\Console\Tasks;

use Modules\Comms\Domain\Actions\EscalateUnreadUrgentNoticeAction;
use Modules\Comms\Models\Notice;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;

/**
 * Scheduled (COM-06 BR-COM-06-005): alerts the poster of any published urgent notice with a low read rate.
 */
final class EscalateUnreadUrgentNoticesTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $count = 0;

        foreach (Notice::query()->where('priority', 'urgent')->where('status', 'published')->pluck('id') as $noticeId) {
            if (app(EscalateUnreadUrgentNoticeAction::class)->execute((int) $noticeId)) {
                $count++;
            }
        }

        return "{$count} notice(s) escalated";
    }
}
