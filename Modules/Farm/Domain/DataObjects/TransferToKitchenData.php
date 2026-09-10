<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class TransferToKitchenData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $productionUnitId,
        public int $fromStoreId,
        public int $toStoreId,
        public int $itemId,
        public float $quantity,
        public string $unit,
        public CarbonInterface $transferDate,
        public int $dispatchedByUserId,
        public ?int $harvestId = null,
        public ?int $outputId = null,
        public ?int $marketPriceMinor = null,
    ) {}
}
