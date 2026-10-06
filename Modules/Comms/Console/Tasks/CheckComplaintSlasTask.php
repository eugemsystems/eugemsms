<?php

declare(strict_types=1);

namespace Modules\Comms\Console\Tasks;

use Modules\Comms\Domain\Actions\CheckComplaintSlaAction;
use Modules\Comms\Models\Complaint;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;

/**
 * Scheduled (COM-08 BR-COM-08-004): warns assignees of approaching complaint SLAs and their managers of breaches.
 */
final class CheckComplaintSlasTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $count = 0;

        foreach (Complaint::query()->whereNotIn('status', ['resolved', 'closed'])->whereNotNull('assigned_to_staff_id')->pluck('id') as $complaintId) {
            app(CheckComplaintSlaAction::class)->execute((int) $complaintId);
            $count++;
        }

        return "{$count} complaint(s) checked";
    }
}
