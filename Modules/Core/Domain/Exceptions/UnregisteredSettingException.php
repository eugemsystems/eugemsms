<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-04-001: a setting must be registered before a value can be
 * read or stored for it.
 */
class UnregisteredSettingException extends DomainException
{
    public function errorCode(): string
    {
        return 'UNREGISTERED_SETTING';
    }
}
