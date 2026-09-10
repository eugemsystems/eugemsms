<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

/**
 * BR-OPS-03-017. `totalCostMinor` is the production unit's own
 * accumulated crop-cycle cost for the period — livestock acquisition/
 * event cost isn't rolled in here since it has no per-cycle
 * equivalent to sum against; a unit's own cost centre ledger already
 * carries that detail for anyone who needs it.
 */
final readonly class ProductionUnitProfitabilityResult
{
    public function __construct(
        public int $productionUnitId,
        public int $totalCropCostMinor,
        public int $kitchenTransferValueMinor,
        public int $externalSalesMinor,
        public string $currency,
    ) {}

    public function netPositionMinor(): int
    {
        return $this->kitchenTransferValueMinor + $this->externalSalesMinor - $this->totalCropCostMinor;
    }
}
