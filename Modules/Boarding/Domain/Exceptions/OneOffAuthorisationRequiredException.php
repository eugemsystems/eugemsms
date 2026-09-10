<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book F BRD-03 §5/BR-BRD-03-014. A one-off authorised collector
 * requires explicit authorisation by a guardian holding
 * `may_authorise_exeat` on this learner.
 */
class OneOffAuthorisationRequiredException extends DomainException
{
    public static function forGuardian(int $studentId, int $guardianId): self
    {
        return new self(
            "Guardian #{$guardianId} does not hold may_authorise_exeat for student #{$studentId} and cannot authorise a one-off collector.",
            ['student_id' => $studentId, 'guardian_id' => $guardianId],
        );
    }

    public function errorCode(): string
    {
        return 'ONE_OFF_AUTHORISATION_REQUIRED';
    }
}
