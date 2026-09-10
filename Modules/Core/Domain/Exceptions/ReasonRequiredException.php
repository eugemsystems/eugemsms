<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * A mandatory free-text reason (impersonation, voiding an allocated
 * number, reopening a locked period, ...) was blank or missing.
 */
class ReasonRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'REASON_REQUIRED';
    }
}
