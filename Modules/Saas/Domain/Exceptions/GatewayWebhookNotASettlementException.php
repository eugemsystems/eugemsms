<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

final class GatewayWebhookNotASettlementException extends DomainException
{
    public function errorCode(): string
    {
        return 'GATEWAY_WEBHOOK_NOT_A_SETTLEMENT';
    }
}
