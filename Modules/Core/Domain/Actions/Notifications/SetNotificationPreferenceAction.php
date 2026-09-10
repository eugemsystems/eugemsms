<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Notifications;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Notifications\SetNotificationPreferenceData;
use Modules\Core\Models\NotificationPreference;

final class SetNotificationPreferenceAction extends Action
{
    public function execute(SetNotificationPreferenceData $data): NotificationPreference
    {
        return $this->transaction(fn (): NotificationPreference => NotificationPreference::updateOrCreate(
            [
                'school_id' => $data->schoolId,
                'user_id' => $data->userId,
                'notification_key' => $data->notificationKey,
                'channel' => $data->channel,
            ],
            ['is_enabled' => $data->isEnabled],
        ));
    }
}
