<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book J SAA-01 §4/BR-SAA-01-004 — a tenant has exceeded a hard usage
 * limit for the current billing period. Refuses only the specific
 * over-limit action (e.g. new enrolment), never a blanket lockout.
 */
final class UsageLimitExceededException extends DomainException
{
    public function errorCode(): string
    {
        return 'USAGE_LIMIT_EXCEEDED';
    }
}
