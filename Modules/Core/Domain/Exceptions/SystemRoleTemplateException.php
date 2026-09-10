<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-05-013 — a system role template (`is_system = 1`) cannot be
 * deleted or renamed. It can be cloned and the clone modified freely.
 */
class SystemRoleTemplateException extends DomainException
{
    public function errorCode(): string
    {
        return 'SYSTEM_ROLE_TEMPLATE';
    }
}
