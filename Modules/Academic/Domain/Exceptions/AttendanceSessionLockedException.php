<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-04 §4/BR-ACA-04-009. Amending a session older than
 * `attendance.lock_after_hours` requires `academic.attendance.amend_locked`
 * — enforced here by the caller passing `overrideLock`, the same
 * pattern `AllocateTeacherAction`'s `overrideCeiling` uses.
 */
class AttendanceSessionLockedException extends DomainException
{
    public static function forSession(int $sessionId, int $lockAfterHours): self
    {
        return new self(
            "This attendance session locked after {$lockAfterHours} hour(s) and cannot be amended without an override.",
            ['session_id' => $sessionId, 'lock_after_hours' => $lockAfterHours],
        );
    }

    public function errorCode(): string
    {
        return 'ATTENDANCE_SESSION_LOCKED';
    }
}
