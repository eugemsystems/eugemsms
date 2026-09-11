<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

use Illuminate\Support\Carbon;

final readonly class RecordTenantPaymentData
{
    public function __construct(
        public int $invoiceId,
        public int $amountMinor,
        public string $currency,
        public string $paymentMethod,
        public ?string $gatewayReference = null,
        public ?Carbon $receivedAt = null,
    ) {}
}
