<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateTimetableSlotData
{
    public function __construct(
        public int $timetableId,
        public int $termId,
        public int $periodSlotId,
        public int $cycleDay,
        public int $periodNumber,
        public int $subjectId,
        public int $staffId,
        public ?int $classId = null,
        public ?int $teachingGroupId = null,
        public ?int $coStaffId = null,
        public ?int $venueId = null,
        public bool $isDouble = false,
        public ?int $doublePartnerSlotId = null,
        public bool $isLocked = false,
        public ?string $notes = null,
    ) {}
}
