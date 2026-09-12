<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Notifications;

final readonly class RetryNotificationData
{
    public function __construct(
        public int $notificationId,
    ) {}
}
