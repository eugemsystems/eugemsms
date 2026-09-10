<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class PlanCropCycleData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $productionUnitId,
        public int $fieldId,
        public string $cycleReference,
        public string $crop,
        public string $season,
        public float $areaPlantedHectares,
        public string $currency,
        public ?string $variety = null,
        public ?CarbonInterface $plantedOn = null,
        public ?CarbonInterface $expectedHarvestOn = null,
        public ?float $expectedYieldKg = null,
    ) {}
}
