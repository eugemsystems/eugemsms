<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ScheduleDetentionData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $studentId,
        public CarbonInterface $scheduledDate,
        public string $startsAt,
        public string $endsAt,
        public ?int $sanctionId = null,
        public ?string $venue = null,
        public ?int $supervisorStaffId = null,
        public ?string $taskSet = null,
    ) {}
}
