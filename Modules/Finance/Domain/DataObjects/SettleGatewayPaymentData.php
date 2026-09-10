<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class SettleGatewayPaymentData
{
    public function __construct(
        public int $paymentIntentId,
        public int $processedByUserId,
        public string $gatewayReference,
        public int $amountMinor,
        public ?int $feeMinor = null,
        public ?int $creditBalanceAccountId = null,
        public ?int $suspenseAccountId = null,
    ) {}
}
