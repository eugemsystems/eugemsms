<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RunReconciliationData
{
    /**
     * @param  array<int, array{gateway_reference: string, amount_minor: int, currency: string, fee_minor?: int|null}>  $gatewaySettlements  what the gateway itself reports settled for this run's window — stands in for a real settlement-report API call
     */
    public function __construct(
        public int $schoolId,
        public CarbonInterface $runDate,
        public string $scope,
        public string $currency,
        public array $gatewaySettlements = [],
        public ?int $gatewayId = null,
        public ?int $bankAccountId = null,
    ) {}
}
