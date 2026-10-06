<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class ImpersonationReadOnlyException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'IMPERSONATION_READ_ONLY';
    }
}
