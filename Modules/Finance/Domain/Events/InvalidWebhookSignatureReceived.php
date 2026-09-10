<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\GatewayWebhook;

/**
 * Book B FIN-05 §6/BR-FIN-05-003 (AC-FIN-05-002). "A security event
 * raised" — this is that event; a listener would forward it to
 * whatever security-alerting channel the school has, once one exists.
 */
final class InvalidWebhookSignatureReceived
{
    public function __construct(
        public readonly GatewayWebhook $webhook,
    ) {}
}
