<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Contracts;

use Modules\Comms\Domain\DataObjects\HealthStatus;
use Modules\Comms\Domain\DataObjects\MessagingWebhookEvent;

/**
 * Book I COM-01 §2/BR-COM-01-010/011. The concerns Book A's own
 * `Modules\Core\Domain\Contracts\Notifications\NotificationChannelDriver`
 * deliberately doesn't need (webhook verification/parsing, health
 * checks) — mirrors `Modules\Finance\Domain\Contracts\PaymentGatewayDriver`'s
 * own `verifyWebhook`/`parseWebhook`/`healthCheck` shape. Every real
 * `NotificationChannelDriver` this module registers additionally
 * implements this contract; `IngestMessagingWebhookAction` and
 * `CheckGatewayHealthAction` both type against THIS interface, not
 * Core's, since Core's own contract has no such methods.
 */
interface MessagingGatewayDriver
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhook(array $headers, string $body): bool;

    /**
     * @param  array<string, mixed>  $headers
     */
    public function parseWebhook(array $headers, string $body): MessagingWebhookEvent;

    public function healthCheck(): HealthStatus;
}
