<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-08 §4/BR-ACA-08-007. Resubmission is only possible where
 * `allows_resubmission = 1`.
 */
class ResubmissionNotAllowedException extends DomainException
{
    public static function forAssignment(int $assignmentId, int $studentId): self
    {
        return new self(
            "Assignment #{$assignmentId} does not allow resubmission, and student #{$studentId} has already submitted.",
            ['assignment_id' => $assignmentId, 'student_id' => $studentId],
        );
    }

    public function errorCode(): string
    {
        return 'RESUBMISSION_NOT_ALLOWED';
    }
}
