<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

/**
 * A driver-parsed webhook body — `IngestMessagingWebhookAction` never
 * reads `raw_payload` itself, only what the driver extracted from it.
 * Covers both delivery-status callbacks (`delivered`/`read`/`failed`)
 * and inbound messages (`inbound_message`), matching COM-01 §2's own
 * `gateway_webhooks.event_type` enum.
 */
final readonly class MessagingWebhookEvent
{
    public function __construct(
        public string $eventType,
        public ?string $providerMessageId = null,
        public ?string $status = null,
        public ?string $fromAddress = null,
        public ?string $body = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}

    public function isDeliveryStatus(): bool
    {
        return in_array($this->eventType, ['delivered', 'read', 'failed'], true);
    }

    public function isInboundMessage(): bool
    {
        return $this->eventType === 'inbound_message';
    }
}
