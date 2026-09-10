<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordProductionOutputData
{
    public function __construct(
        public int $schoolId,
        public int $productionUnitId,
        public CarbonInterface $outputDate,
        public string $outputType,
        public float $quantity,
        public string $unit,
        public string $currency,
        public int $recordedByUserId,
        public string $destination = 'kitchen',
        public ?int $unitCostMinor = null,
    ) {}
}
