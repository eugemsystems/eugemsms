<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class InvalidTwoFactorCodeException extends DomainException
{
    public function errorCode(): string
    {
        return 'INVALID_TWO_FACTOR_CODE';
    }
}
