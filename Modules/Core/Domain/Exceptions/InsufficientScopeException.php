<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class InsufficientScopeException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'INSUFFICIENT_SCOPE';
    }
}
