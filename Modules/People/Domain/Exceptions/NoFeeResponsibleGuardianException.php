<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-03 §4/BR-PPL-03-004. Every learner must have at least one
 * `is_fee_responsible` guardian to absorb the residual — pass 3 of the
 * resolution algorithm has nowhere to assign an unclaimed remainder
 * without one.
 */
class NoFeeResponsibleGuardianException extends DomainException
{
    public static function forStudent(int $studentId): self
    {
        return new self(
            "Student [{$studentId}] has no is_fee_responsible guardian to absorb the unallocated balance (BR-PPL-03-004).",
            ['student_id' => $studentId],
        );
    }

    public function errorCode(): string
    {
        return 'NO_FEE_RESPONSIBLE_GUARDIAN';
    }
}
