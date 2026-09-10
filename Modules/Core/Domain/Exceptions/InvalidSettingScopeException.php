<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-04-003: a value cannot be set at a scope more general than the
 * definition's `lowest_scope`.
 */
class InvalidSettingScopeException extends DomainException
{
    public function errorCode(): string
    {
        return 'INVALID_SETTING_SCOPE';
    }
}
