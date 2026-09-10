<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class ModuleNotEnabledException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'MODULE_NOT_ENABLED';
    }
}
