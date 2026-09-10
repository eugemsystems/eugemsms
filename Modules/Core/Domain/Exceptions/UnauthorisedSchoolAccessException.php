<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class UnauthorisedSchoolAccessException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'UNAUTHORISED_SCHOOL_ACCESS';
    }
}
