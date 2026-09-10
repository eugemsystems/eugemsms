<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class ReconcileGatewayCostData
{
    public function __construct(
        public int $schoolId,
        public int $gatewayId,
        public string $periodMonth,
        public ?int $providerInvoicedMinor = null,
    ) {}
}
