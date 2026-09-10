<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Notifications;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Notification;

final class MarkNotificationReadAction extends Action
{
    public function execute(Notification $notification): Notification
    {
        return $this->transaction(function () use ($notification): Notification {
            if ($notification->read_at === null) {
                $notification->forceFill(['read_at' => Carbon::now()])->save();
            }

            return $notification;
        });
    }
}
