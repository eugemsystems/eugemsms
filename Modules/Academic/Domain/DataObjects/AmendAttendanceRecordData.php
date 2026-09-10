<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class AmendAttendanceRecordData
{
    public function __construct(
        public int $recordId,
        public string $newStatus,
        public int $amendedByUserId,
        public string $amendmentReason,
        public ?int $reasonCodeId = null,
        public ?int $minutesLate = null,
        public ?string $note = null,
        public bool $overrideLock = false,
    ) {}
}
