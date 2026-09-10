<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

final readonly class SavingsReportResult
{
    public function __construct(
        public int $marketValueMinor,
        public int $internalCostMinor,
        public string $currency,
    ) {}

    public function savingsMinor(): int
    {
        return $this->marketValueMinor - $this->internalCostMinor;
    }
}
