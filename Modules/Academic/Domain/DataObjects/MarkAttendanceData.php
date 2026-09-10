<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class MarkAttendanceData
{
    /**
     * @param  array<int, MarkAttendanceRecordInput>  $records
     */
    public function __construct(
        public int $sessionId,
        public array $records,
        public int $markedByUserId,
    ) {}
}
