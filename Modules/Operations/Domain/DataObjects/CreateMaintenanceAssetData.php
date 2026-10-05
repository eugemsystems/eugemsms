<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\DataObjects;

final readonly class CreateMaintenanceAssetData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $assetType,
        public int $costCentreId,
        public ?string $location = null,
        public ?string $building = null,
        public string $criticality = 'normal',
        public string $condition = 'good',
        public ?int $fixedAssetId = null,
        public ?int $vehicleId = null,
        public ?int $serviceIntervalDays = null,
        public ?float $serviceIntervalUnits = null,
    ) {}
}
