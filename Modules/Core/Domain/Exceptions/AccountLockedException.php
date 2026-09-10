<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-05-006. Also used for a non-`active` account status (suspended,
 * inactive, pending) — the caller does not need to distinguish "locked
 * out from too many failures" from "administratively suspended".
 */
class AccountLockedException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'ACCOUNT_LOCKED';
    }
}
