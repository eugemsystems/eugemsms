<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\CustomReportSchedule;

/**
 * ACT-DeleteCustomReportSchedule (Book J INT-01 §5 screen table,
 * "schedule delete"). A hard delete of the recurring-delivery
 * definition; the report itself and anything already delivered are
 * untouched.
 */
final class DeleteCustomReportScheduleAction extends Action
{
    public function execute(int $scheduleId): void
    {
        $schedule = CustomReportSchedule::findOrFail($scheduleId);

        $this->transaction(function () use ($schedule): void {
            $schedule->delete();
        });
    }
}
