<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ReceiveStockData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $storeId,
        public int $itemId,
        public float $quantity,
        public int $unitCostMinor,
        public string $currency,
        public CarbonInterface $receivedOn,
        public int $performedByUserId,
        public int $contraAccountId,
        public string $sourceType = 'opening',
        public ?int $sourceId = null,
        public ?string $lotReference = null,
        public ?string $batchNumber = null,
        public ?CarbonInterface $expiryDate = null,
        public ?int $baseUnitCostMinor = null,
        public ?int $exchangeRateId = null,
    ) {}
}
