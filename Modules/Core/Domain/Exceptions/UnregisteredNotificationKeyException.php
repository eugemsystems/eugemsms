<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class UnregisteredNotificationKeyException extends DomainException
{
    public function errorCode(): string
    {
        return 'UNREGISTERED_NOTIFICATION_KEY';
    }
}
