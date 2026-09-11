<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

final class SchemeNotApplicationBasedException extends DomainException
{
    public function errorCode(): string
    {
        return 'SCHEME_NOT_APPLICATION_BASED';
    }
}
