<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

final class RollbackNoLongerAvailableException extends DomainException
{
    public function errorCode(): string
    {
        return 'ROLLBACK_NO_LONGER_AVAILABLE';
    }
}
