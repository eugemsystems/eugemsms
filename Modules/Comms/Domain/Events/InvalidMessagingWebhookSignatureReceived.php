<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\MessagingGatewayWebhook;

final class InvalidMessagingWebhookSignatureReceived
{
    public function __construct(
        public readonly MessagingGatewayWebhook $webhook,
    ) {}
}
