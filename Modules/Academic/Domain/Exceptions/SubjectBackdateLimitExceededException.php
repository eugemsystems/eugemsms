<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-02 §5/BR-ACA-02-006. `effective_from` may be backdated
 * within `academic.backdate_limit_days`, or when backdating is
 * disabled outright — the same shape as People's
 * `BackdateLimitExceededException` (Book C PPL-01), kept as a separate
 * class per module rather than a shared cross-module dependency.
 */
class SubjectBackdateLimitExceededException extends DomainException
{
    public static function forDays(int $requestedDays, int $limitDays): self
    {
        return new self(
            "effective_from is {$requestedDays} day(s) in the past, beyond the {$limitDays}-day backdate limit.",
            ['requested_days' => $requestedDays, 'limit_days' => $limitDays],
        );
    }

    public static function backdatingDisabled(): self
    {
        return new self(
            'Backdated subject enrolment changes are disabled for this school.',
            [],
        );
    }

    public function errorCode(): string
    {
        return 'SUBJECT_BACKDATE_LIMIT_EXCEEDED';
    }
}
