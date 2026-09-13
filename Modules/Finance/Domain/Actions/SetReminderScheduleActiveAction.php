<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\SetReminderScheduleActiveData;
use Modules\Finance\Models\ReminderSchedule;

final class SetReminderScheduleActiveAction extends Action
{
    public function execute(SetReminderScheduleActiveData $data): ReminderSchedule
    {
        $schedule = ReminderSchedule::findOrFail($data->reminderScheduleId);

        return $this->transaction(fn (): ReminderSchedule => tap($schedule)->update(['is_active' => $data->isActive]));
    }
}
