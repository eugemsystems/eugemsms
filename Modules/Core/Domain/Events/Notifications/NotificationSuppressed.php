<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Notifications;

use Modules\Core\Models\Notification;

final class NotificationSuppressed
{
    public function __construct(
        public readonly Notification $notification,
        public readonly string $reason,
    ) {}
}
