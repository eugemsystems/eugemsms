<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-11-007 — either the definition is not `is_rollbackable`, or
 * a created record has since been modified or referenced.
 */
class ImportNotRollbackableException extends DomainException
{
    public function errorCode(): string
    {
        return 'IMPORT_NOT_ROLLBACKABLE';
    }
}
