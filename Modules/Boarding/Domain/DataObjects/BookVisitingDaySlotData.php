<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class BookVisitingDaySlotData
{
    public function __construct(
        public int $visitingDayId,
        public int $studentId,
        public int $guardianId,
        public string $slotStartsAt,
        public int $partySize = 1,
    ) {}
}
