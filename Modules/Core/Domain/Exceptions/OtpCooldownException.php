<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class OtpCooldownException extends DomainException
{
    public function errorCode(): string
    {
        return 'OTP_COOLDOWN';
    }
}
