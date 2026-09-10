<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Notifications;

/**
 * BR-CORE-09-008/AC-CORE-09-003 — the school administrator and bursar
 * are alerted immediately.
 */
final class NotificationBudgetCapReached
{
    public function __construct(
        public readonly int $schoolId,
        public readonly string $channel,
    ) {}
}
