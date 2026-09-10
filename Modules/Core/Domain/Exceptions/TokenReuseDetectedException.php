<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-05-009/AC-CORE-05-002 — an already-used refresh token was
 * presented again. The entire device token family has already been
 * revoked by the time this is thrown.
 */
class TokenReuseDetectedException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'TOKEN_REUSE_DETECTED';
    }
}
