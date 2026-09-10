<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class MissingSessionContextException extends ContextException
{
    public function errorCode(): string
    {
        return 'MISSING_SESSION_CONTEXT';
    }
}
