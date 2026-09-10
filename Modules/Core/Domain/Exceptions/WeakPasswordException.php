<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class WeakPasswordException extends DomainException
{
    public function errorCode(): string
    {
        return 'WEAK_PASSWORD';
    }
}
