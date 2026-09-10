<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-07-004/AC-CORE-07-002 — a user cannot approve their own
 * request, regardless of role.
 */
class SelfApprovalNotPermittedException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'SELF_APPROVAL_NOT_PERMITTED';
    }
}
