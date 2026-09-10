<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\DataObjects;

/**
 * BR-OPS-01-018. Driver cost is not included — this schema carries no
 * per-route driver salary allocation to aggregate; only fuel,
 * maintenance, depreciation and compliance cost, against fee income.
 */
final readonly class RouteCostingResult
{
    public function __construct(
        public int $routeId,
        public int $fuelCostMinor,
        public int $maintenanceCostMinor,
        public int $complianceCostMinor,
        public int $depreciationMinor,
        public int $feeIncomeMinor,
        public string $currency,
    ) {}

    public function totalCostMinor(): int
    {
        return $this->fuelCostMinor + $this->maintenanceCostMinor + $this->complianceCostMinor + $this->depreciationMinor;
    }

    public function marginMinor(): int
    {
        return $this->feeIncomeMinor - $this->totalCostMinor();
    }
}
