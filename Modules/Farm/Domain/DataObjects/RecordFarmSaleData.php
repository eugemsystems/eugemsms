<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordFarmSaleData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $productionUnitId,
        public CarbonInterface $saleDate,
        public string $buyerName,
        public string $itemDescription,
        public float $quantity,
        public string $unit,
        public int $unitPriceMinor,
        public string $currency,
        public int $cashAccountId,
        public int $salesIncomeAccountId,
        public int $performedByUserId,
        public ?string $buyerContact = null,
        public ?int $costOfSalesMinor = null,
        public ?int $inventoryAccountId = null,
    ) {}
}
