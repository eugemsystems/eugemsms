<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Notifications;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Notifications\SetNotificationBudgetData;
use Modules\Core\Models\NotificationBudget;

final class SetNotificationBudgetAction extends Action
{
    public function execute(SetNotificationBudgetData $data): NotificationBudget
    {
        return $this->transaction(fn (): NotificationBudget => NotificationBudget::updateOrCreate(
            ['school_id' => $data->schoolId, 'period_month' => $data->periodMonth, 'channel' => $data->channel],
            [
                'currency' => $data->currency,
                'cap_minor' => $data->capMinor,
                'warn_at_percent' => $data->warnAtPercent,
                'is_hard_stop' => $data->isHardStop,
            ],
        ));
    }
}
