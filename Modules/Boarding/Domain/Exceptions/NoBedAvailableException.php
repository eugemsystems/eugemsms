<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book F BRD-01 §3/§4. Every hard-valid candidate bed was exhausted;
 * `reason` names the blocking constraint that stopped the last one
 * considered, never silently dropping the learner.
 */
class NoBedAvailableException extends DomainException
{
    public static function forStudent(int $studentId, string $reason): self
    {
        return new self(
            "No bed is available for student #{$studentId}: {$reason}.",
            ['student_id' => $studentId, 'reason' => $reason],
        );
    }

    public function errorCode(): string
    {
        return 'NO_BED_AVAILABLE';
    }
}
