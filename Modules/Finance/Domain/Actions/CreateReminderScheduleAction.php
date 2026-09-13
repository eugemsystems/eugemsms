<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CreateReminderScheduleData;
use Modules\Finance\Models\ReminderSchedule;

final class CreateReminderScheduleAction extends Action
{
    public function execute(CreateReminderScheduleData $data): ReminderSchedule
    {
        return $this->transaction(fn (): ReminderSchedule => ReminderSchedule::create([
            'school_id' => $data->schoolId,
            'name' => $data->name,
            'days_after_due' => $data->daysAfterDue,
            'minimum_balance_minor' => $data->minimumBalanceMinor,
            'currency' => $data->currency,
            'channels' => $data->channels,
            'template_key' => $data->templateKey,
            'audience' => $data->audience,
            'escalate_to_role_id' => $data->escalateToRoleId,
            'is_active' => true,
        ]));
    }
}
