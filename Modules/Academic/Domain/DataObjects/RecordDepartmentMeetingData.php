<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordDepartmentMeetingData
{
    /**
     * @param  array<int, int>  $attendeeStaffIds
     * @param  array<int, array{action: string, owner: int, due_date: string, status?: string}>|null  $actionItems
     */
    public function __construct(
        public int $schoolId,
        public int $departmentId,
        public CarbonInterface $meetingDate,
        public array $attendeeStaffIds,
        public string $minutes,
        public int $chairedByStaffId,
        public ?string $agenda = null,
        public ?array $actionItems = null,
    ) {}
}
