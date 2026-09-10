<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

final readonly class CreateMeterData
{
    public function __construct(
        public int $schoolId,
        public int $utilityAccountId,
        public string $meterNumber,
        public string $meterType,
        public string $location,
        public string $servesScope,
        public string $unit,
        public ?int $scopeId = null,
        public ?int $costCentreId = null,
        public float $multiplier = 1.0,
        public ?float $lowBalanceThreshold = null,
    ) {}
}
