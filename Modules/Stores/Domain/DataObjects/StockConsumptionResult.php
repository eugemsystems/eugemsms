<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Illuminate\Support\Collection;

final readonly class StockConsumptionResult
{
    /**
     * @param  Collection<int, StockLotConsumption>  $consumptions
     */
    public function __construct(
        public Collection $consumptions,
        public float $shortfallQuantity,
    ) {}

    public function totalCostMinor(): int
    {
        return (int) $this->consumptions->sum('costMinor');
    }

    public function totalQuantityConsumed(): float
    {
        return (float) $this->consumptions->sum('quantity');
    }

    public function isFullyConsumed(): bool
    {
        return $this->shortfallQuantity <= 0.0;
    }
}
