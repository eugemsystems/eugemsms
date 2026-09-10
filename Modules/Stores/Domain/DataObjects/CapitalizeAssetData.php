<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CapitalizeAssetData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $categoryId,
        public string $name,
        public CarbonInterface $acquisitionDate,
        public int $acquisitionCostMinor,
        public string $currency,
        public string $acquisitionSource,
        public int $costCentreId,
        public int $contraAccountId,
        public int $performedByUserId,
        public ?int $supplierId = null,
        public ?int $purchaseOrderId = null,
        public ?int $grnId = null,
        public ?int $stockMovementId = null,
        public ?string $donorName = null,
        public ?string $depreciationMethod = null,
        public ?float $usefulLifeYears = null,
        public ?int $residualValueMinor = null,
        public ?float $totalUnitsExpected = null,
        public ?int $departmentId = null,
        public ?int $custodianStaffId = null,
    ) {}
}
