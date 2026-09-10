<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class InsufficientBalanceException extends DomainException
{
    public function errorCode(): string
    {
        return 'INSUFFICIENT_BALANCE';
    }
}
