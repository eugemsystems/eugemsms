<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects\Gateway;

final readonly class RefundResponse
{
    public function __construct(
        public bool $success,
        public ?string $gatewayReference = null,
        public ?string $failureMessage = null,
    ) {}
}
