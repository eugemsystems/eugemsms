<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-05-001 — a user must have at least one of email, phone, or
 * username.
 */
class IncompleteUserIdentityException extends DomainException
{
    public function errorCode(): string
    {
        return 'INCOMPLETE_USER_IDENTITY';
    }
}
