<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book F BRD-01 §3/§4 ⭐/BR-BRD-01-001/AC-BRD-01-001. Gender
 * segregation is a hard constraint with no override path anywhere in
 * this codebase — this exception has no accompanying "override"
 * parameter on any Action, and never will.
 */
class GenderMismatchException extends DomainException
{
    public static function forHostel(int $studentId, int $hostelId): self
    {
        return new self(
            "Student #{$studentId}'s gender does not match hostel #{$hostelId}'s. There is no override for this.",
            ['student_id' => $studentId, 'hostel_id' => $hostelId],
        );
    }

    public function errorCode(): string
    {
        return 'GENDER_MISMATCH';
    }
}
