<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-08 §4/BR-ACA-08-004. A submission after `due_at` follows
 * the assignment's declared `late_policy` — this is the `block`
 * branch, the only one that refuses the submission outright.
 */
class AssignmentSubmissionBlockedException extends DomainException
{
    public static function forAssignment(int $assignmentId): self
    {
        return new self(
            "Assignment #{$assignmentId}'s late policy blocks submissions after the due date.",
            ['assignment_id' => $assignmentId],
        );
    }

    public function errorCode(): string
    {
        return 'ASSIGNMENT_SUBMISSION_BLOCKED';
    }
}
