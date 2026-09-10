<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

final readonly class CreateFarmFieldData
{
    public function __construct(
        public int $schoolId,
        public int $productionUnitId,
        public string $code,
        public string $name,
        public float $areaHectares,
        public ?string $soilType = null,
        public bool $isIrrigated = false,
        public ?string $irrigationType = null,
        public ?int $waterSourceId = null,
    ) {}
}
