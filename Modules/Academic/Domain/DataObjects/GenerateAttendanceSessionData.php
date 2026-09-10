<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GenerateAttendanceSessionData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public CarbonInterface $sessionDate,
        public string $mode,
        public ?int $classId = null,
        public ?int $teachingGroupId = null,
        public ?int $subjectId = null,
        public ?int $periodNumber = null,
        public ?string $deviceSource = null,
        public ?int $staffId = null,
        public ?int $timetableSlotId = null,
    ) {}
}
