<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * The acting user is neither a resolved approver for the current step
 * nor an active delegate of one.
 */
class ApproverNotAuthorizedException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'APPROVER_NOT_AUTHORIZED';
    }
}
