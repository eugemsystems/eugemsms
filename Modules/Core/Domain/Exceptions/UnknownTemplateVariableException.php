<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-06-010/AC-CORE-06-004 — a template referenced a variable not
 * in its type's registered set.
 */
class UnknownTemplateVariableException extends DomainException
{
    public function errorCode(): string
    {
        return 'UNKNOWN_TEMPLATE_VARIABLE';
    }
}
