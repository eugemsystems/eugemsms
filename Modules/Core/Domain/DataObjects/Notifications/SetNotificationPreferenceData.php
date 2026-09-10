<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Notifications;

final readonly class SetNotificationPreferenceData
{
    public function __construct(
        public int $schoolId,
        public int $userId,
        public string $channel,
        public bool $isEnabled,
        public ?string $notificationKey = null,
    ) {}
}
