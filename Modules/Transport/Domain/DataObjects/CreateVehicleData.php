<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\DataObjects;

final readonly class CreateVehicleData
{
    public function __construct(
        public int $schoolId,
        public string $fleetNumber,
        public string $registrationNumber,
        public string $vehicleType,
        public int $seatingCapacity,
        public string $fuelType,
        public int $costCentreId,
        public ?string $make = null,
        public ?string $model = null,
        public ?int $yearOfManufacture = null,
        public int $standingCapacity = 0,
        public ?float $tankCapacityLitres = null,
        public ?float $expectedKmPerLitre = null,
        public ?int $fixedAssetId = null,
        public ?int $maintenanceAssetId = null,
        public ?string $trackerDeviceId = null,
    ) {}
}
