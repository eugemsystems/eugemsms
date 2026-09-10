<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class GatewayUnreachableException extends IntegrationException
{
    public function errorCode(): string
    {
        return 'GATEWAY_UNREACHABLE';
    }
}
