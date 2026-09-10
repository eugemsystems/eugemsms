<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class DuplicateRecordException extends DomainException
{
    public function errorCode(): string
    {
        return 'DUPLICATE_RECORD';
    }
}
