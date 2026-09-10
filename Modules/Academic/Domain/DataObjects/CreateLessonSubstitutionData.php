<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateLessonSubstitutionData
{
    public function __construct(
        public int $timetableSlotId,
        public CarbonInterface $substitutionDate,
        public int $absentStaffId,
        public string $reason,
        public ?int $leaveRequestId = null,
    ) {}
}
