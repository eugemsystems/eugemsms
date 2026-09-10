<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-04 §4/BR-PPL-04-016. Both parties must consent before a
 * duty swap is recorded — this pass represents that as an explicit
 * DTO flag rather than a real capture-and-store consent flow.
 */
class DutySwapConsentRequiredException extends DomainException
{
    public static function forAssignment(int $assignmentId): self
    {
        return new self(
            "Duty assignment [{$assignmentId}] cannot be swapped without both parties' consent.",
            ['assignment_id' => $assignmentId],
        );
    }

    public function errorCode(): string
    {
        return 'DUTY_SWAP_CONSENT_REQUIRED';
    }
}
