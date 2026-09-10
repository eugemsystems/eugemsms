<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects\Gateway;

/**
 * The result of `PaymentGatewayDriver::poll()` — `status` matches
 * `payment_intents.status`'s vocabulary (`pending|processing|succeeded|
 * failed|cancelled|expired`).
 */
final readonly class PaymentStatusResult
{
    public function __construct(
        public string $status,
        public ?int $feeMinor = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public ?string $gatewayReference = null,
    ) {}

    public function isSettled(): bool
    {
        return $this->status === 'succeeded';
    }
}
