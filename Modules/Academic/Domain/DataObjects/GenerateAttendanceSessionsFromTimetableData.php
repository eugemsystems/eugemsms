<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GenerateAttendanceSessionsFromTimetableData
{
    public function __construct(
        public int $timetableId,
        public CarbonInterface $fromDate,
        public CarbonInterface $toDate,
    ) {}
}
