<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class InvalidSessionContextException extends ContextException
{
    public function errorCode(): string
    {
        return 'INVALID_SESSION_CONTEXT';
    }
}
