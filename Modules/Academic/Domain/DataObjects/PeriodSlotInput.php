<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class PeriodSlotInput
{
    public function __construct(
        public int $cycleDay,
        public int $periodNumber,
        public string $label,
        public string $slotType,
        public string $startsAt,
        public string $endsAt,
        public int $durationMinutes,
        public bool $isTeachable = true,
        public bool $requiresAttendance = true,
    ) {}
}
