<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Notifications;

final readonly class RecordDeliveryStatusData
{
    public function __construct(
        public int $notificationId,
        public string $status,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public bool $isHardBounce = false,
    ) {}
}
