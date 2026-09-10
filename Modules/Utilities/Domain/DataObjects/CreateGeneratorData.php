<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

final readonly class CreateGeneratorData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public float $capacityKva,
        public int $costCentreId,
        public string $fuelType = 'diesel',
        public ?float $tankCapacityLitres = null,
        public ?float $expectedLitresPerHour = null,
        public string $servesScope = 'whole_school',
        public ?int $scopeId = null,
        public ?int $fixedAssetId = null,
        public ?int $maintenanceAssetId = null,
    ) {}
}
