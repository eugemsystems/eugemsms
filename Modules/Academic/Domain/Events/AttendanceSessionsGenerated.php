<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

/**
 * Book E ACA-03 §9.
 */
final class AttendanceSessionsGenerated
{
    public function __construct(
        public readonly int $timetableId,
        public readonly int $createdCount,
        public readonly int $skippedCount,
    ) {}
}
