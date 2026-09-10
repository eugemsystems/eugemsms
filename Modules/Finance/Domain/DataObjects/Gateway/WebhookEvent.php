<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects\Gateway;

/**
 * A driver-parsed webhook body — `IngestGatewayWebhookAction` never
 * reads `raw_payload` itself, only what the driver extracted from it.
 */
final readonly class WebhookEvent
{
    public function __construct(
        public string $eventType,
        public string $gatewayReference,
        public string $status,
        public int $amountMinor,
        public string $currency,
        public ?int $feeMinor = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}

    public function isSettlement(): bool
    {
        return $this->status === 'succeeded';
    }
}
