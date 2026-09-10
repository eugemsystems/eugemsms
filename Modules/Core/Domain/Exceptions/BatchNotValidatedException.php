<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-11-003 — there is no one-click import; the user must
 * explicitly approve a `validated` batch's report before execution.
 */
class BatchNotValidatedException extends DomainException
{
    public function errorCode(): string
    {
        return 'BATCH_NOT_VALIDATED';
    }
}
