<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ScheduleMeetingData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public string $meetingType,
        public int $providerId,
        public string $topic,
        public CarbonInterface $startsAt,
        public int $durationMinutes,
        public ?int $hostStaffId = null,
        public ?int $timetableSlotId = null,
        public bool $waitingRoomEnabled = true,
        public bool $recordingEnabled = false,
    ) {}
}
