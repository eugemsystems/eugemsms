<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordFuelLogData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $vehicleId,
        public CarbonInterface $fuelledAt,
        public float $odometerKm,
        public float $litres,
        public int $unitPriceMinor,
        public string $currency,
        public string $source,
        public int $authorisedByUserId,
        public ?int $driverId = null,
        public ?int $supplierId = null,
        public ?int $storeId = null,
        public ?int $itemId = null,
    ) {}
}
