<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Notifications;

final readonly class SetNotificationBudgetData
{
    public function __construct(
        public int $schoolId,
        public string $periodMonth,
        public string $channel,
        public string $currency,
        public ?int $capMinor = null,
        public int $warnAtPercent = 80,
        public bool $isHardStop = true,
    ) {}
}
