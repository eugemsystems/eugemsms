<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class InvalidTokenException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'INVALID_TOKEN';
    }
}
