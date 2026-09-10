<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

class UnregisteredGatewayDriverException extends DomainException
{
    public static function forKey(string $key): self
    {
        return new self("No payment gateway driver is registered for [{$key}].", ['driver' => $key]);
    }

    public function errorCode(): string
    {
        return 'UNREGISTERED_GATEWAY_DRIVER';
    }
}
