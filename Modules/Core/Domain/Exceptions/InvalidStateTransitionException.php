<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class InvalidStateTransitionException extends DomainException
{
    public function errorCode(): string
    {
        return 'INVALID_STATE_TRANSITION';
    }
}
