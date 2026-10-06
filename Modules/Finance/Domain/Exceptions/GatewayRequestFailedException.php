<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\SerpException;

/**
 * A payment gateway could not be reached or refused a request. Renders 502 with a stable code so
 * a client can say "try again shortly" without parsing the gateway's own message.
 */
class GatewayRequestFailedException extends SerpException
{
    public function errorCode(): string
    {
        return 'GATEWAY_UNAVAILABLE';
    }

    public function httpStatus(): int
    {
        return 502;
    }
}
