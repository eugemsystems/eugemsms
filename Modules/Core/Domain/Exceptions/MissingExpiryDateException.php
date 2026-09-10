<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class MissingExpiryDateException extends DomainException
{
    public function errorCode(): string
    {
        return 'MISSING_EXPIRY_DATE';
    }
}
