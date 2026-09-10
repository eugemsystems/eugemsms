<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Modules\Academic\Models\AttendanceSession;

/**
 * Book D ACA-04 §3/AC-ACA-04-005. `conflicts` is how "the second mark
 * is recorded as an amendment attempt and shown to that teacher"
 * surfaces without a second write ever landing — the first mark always
 * stands and nothing here is persisted beyond this response.
 */
final readonly class MarkAttendanceResult
{
    /**
     * @param  array<int, array{student_id: int, existing_status: string, attempted_status: string}>  $conflicts
     */
    public function __construct(
        public AttendanceSession $session,
        public array $conflicts,
    ) {}
}
