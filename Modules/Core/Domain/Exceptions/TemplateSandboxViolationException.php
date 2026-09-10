<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-06-009 — a template attempted to invoke an unregistered
 * function, access the filesystem, or query the database.
 */
class TemplateSandboxViolationException extends DomainException
{
    public function errorCode(): string
    {
        return 'TEMPLATE_SANDBOX_VIOLATION';
    }
}
