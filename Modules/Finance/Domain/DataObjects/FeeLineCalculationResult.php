<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class FeeLineCalculationResult
{
    public function __construct(
        public string $quantity,
        public ?int $unitRateMinor,
        public int $grossMinor,
        public string $prorationFactor,
        public int $netMinor,
        public string $calculationNote,
        public ?string $sourceReference = null,
    ) {}
}
