<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

final readonly class CreateProductionUnitData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $unitType,
        public int $costCentreId,
        public ?int $managerStaffId = null,
        public ?int $storeId = null,
        public ?float $areaHectares = null,
    ) {}
}
