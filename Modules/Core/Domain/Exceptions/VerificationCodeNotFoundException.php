<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class VerificationCodeNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'VERIFICATION_CODE_NOT_FOUND';
    }
}
