<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-09-003/AC-CORE-09-002 — a template referenced a variable the
 * rendering context doesn't have. The message is never sent with an
 * unresolved placeholder; dispatch fails loudly instead.
 */
class MissingNotificationVariableException extends DomainException
{
    public function errorCode(): string
    {
        return 'MISSING_NOTIFICATION_VARIABLE';
    }
}
