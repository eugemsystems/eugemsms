<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\CustomReportSchedule;

/**
 * ACT-SetCustomReportScheduleActive (Book J INT-01 §5 screen table,
 * "schedule pause"), mirroring `SetWebhookSubscriptionActiveAction`'s
 * own pause/resume shape. `RunScheduledReportsAction` already skips
 * an inactive schedule — pausing just flips the flag it already
 * checks.
 */
final class SetCustomReportScheduleActiveAction extends Action
{
    public function execute(int $scheduleId, bool $isActive): CustomReportSchedule
    {
        $schedule = CustomReportSchedule::findOrFail($scheduleId);

        return $this->transaction(function () use ($schedule, $isActive): CustomReportSchedule {
            $schedule->update(['is_active' => $isActive]);

            return $schedule->fresh();
        });
    }
}
