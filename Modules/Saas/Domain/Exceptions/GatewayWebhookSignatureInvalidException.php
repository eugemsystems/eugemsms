<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\AuthorisationException;

final class GatewayWebhookSignatureInvalidException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'GATEWAY_WEBHOOK_SIGNATURE_INVALID';
    }
}
