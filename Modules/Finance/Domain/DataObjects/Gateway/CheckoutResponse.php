<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects\Gateway;

final readonly class CheckoutResponse
{
    public function __construct(
        public string $checkoutUrl,
        public string $gatewayReference,
    ) {}
}
