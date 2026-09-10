<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

final readonly class CreateWaterSourceData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $sourceType,
        public ?float $depthMetres = null,
        public ?float $yieldLitresPerHour = null,
        public ?string $pumpCapacity = null,
        public ?float $storageCapacityLitres = null,
        public ?int $maintenanceAssetId = null,
    ) {}
}
