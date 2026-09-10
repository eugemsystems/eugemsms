<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-05-018 — an impersonator can never perform a financial
 * mutation, change permissions, or export bulk data.
 */
class ImpersonationNotPermittedException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'IMPERSONATION_NOT_PERMITTED';
    }
}
