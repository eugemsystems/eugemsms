<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

/**
 * BR-OPS-04-011 §4. `gridCostMinor` counts only credited prepaid token
 * spend — postpaid utility invoices arrive through `FIN-08` supplier
 * invoices with no link back to `utility_accounts` in this schema
 * (the same documented boundary `Modules\Transport\Domain\Actions\
 * RecordContractorCostAction` already uses for a cost this module
 * can't complete alone). "Estimated productivity impact" from the
 * spec's own formula is omitted entirely — it has no real source to
 * compute from in this codebase, and a fabricated placeholder number
 * would be worse than leaving it out.
 */
final readonly class OutageCostResult
{
    public function __construct(
        public float $gridKwh,
        public int $gridCostMinor,
        public float $generatorKwh,
        public int $generatorCostMinor,
        public float $outageHours,
        public string $currency,
    ) {}

    public function gridRateMinorPerKwh(): float
    {
        return $this->gridKwh > 0 ? $this->gridCostMinor / $this->gridKwh : 0.0;
    }

    public function generatorRateMinorPerKwh(): float
    {
        return $this->generatorKwh > 0 ? $this->generatorCostMinor / $this->generatorKwh : 0.0;
    }

    public function additionalCostMinor(): int
    {
        return (int) round($this->generatorKwh * ($this->generatorRateMinorPerKwh() - $this->gridRateMinorPerKwh()));
    }
}
