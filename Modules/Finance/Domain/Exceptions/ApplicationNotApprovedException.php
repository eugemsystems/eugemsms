<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

final class ApplicationNotApprovedException extends DomainException
{
    public function errorCode(): string
    {
        return 'SCHOLARSHIP_APPLICATION_NOT_APPROVED';
    }
}
