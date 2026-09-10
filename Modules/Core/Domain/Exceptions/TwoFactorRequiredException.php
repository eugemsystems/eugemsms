<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class TwoFactorRequiredException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'TWO_FACTOR_REQUIRED';
    }
}
