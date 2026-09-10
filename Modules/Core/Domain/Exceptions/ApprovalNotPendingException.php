<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class ApprovalNotPendingException extends DomainException
{
    public function errorCode(): string
    {
        return 'APPROVAL_NOT_PENDING';
    }
}
