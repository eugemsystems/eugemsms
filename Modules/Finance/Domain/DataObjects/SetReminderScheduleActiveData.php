<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class SetReminderScheduleActiveData
{
    public function __construct(
        public int $reminderScheduleId,
        public bool $isActive,
    ) {}
}
