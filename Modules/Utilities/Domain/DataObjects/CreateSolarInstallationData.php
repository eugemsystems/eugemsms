<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateSolarInstallationData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public float $capacityKwp,
        public string $servesScope = 'whole_school',
        public ?float $batteryCapacityKwh = null,
        public ?int $scopeId = null,
        public ?CarbonInterface $commissionedOn = null,
        public ?int $fixedAssetId = null,
        public ?int $maintenanceAssetId = null,
    ) {}
}
