<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-05 §2 — `term_results.class_id` is not nullable per the
 * spec's own schema; a learner with no confirmed `ClassAllocation`
 * for the term genuinely cannot have a term result yet.
 */
class NoCurrentClassAllocationException extends DomainException
{
    public static function forStudent(int $studentId, int $termId): self
    {
        return new self(
            "Student {$studentId} has no confirmed class allocation for term {$termId}.",
            ['student_id' => $studentId, 'term_id' => $termId],
        );
    }

    public function errorCode(): string
    {
        return 'NO_CURRENT_CLASS_ALLOCATION';
    }
}
