<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

final readonly class CreateInventoryItemData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $baseUnit,
        public ?int $categoryId = null,
        public ?string $purchaseUnit = null,
        public float $purchaseConversion = 1.0,
        public ?string $issueUnit = null,
        public float $issueConversion = 1.0,
        public bool $isPerishable = false,
        public bool $requiresBatchTracking = false,
        public ?int $shelfLifeDays = null,
        public bool $isHighRisk = false,
        public bool $isSaleable = false,
        public ?int $salePriceMinor = null,
        public ?string $saleCurrency = null,
        public ?int $saleFeeComponentId = null,
        public bool $isCapitalisable = false,
        public ?int $capitalisationThresholdMinor = null,
        public ?int $expenseAccountId = null,
        public ?int $standardCostMinor = null,
        public ?string $standardCostCurrency = null,
        public ?int $createdByUserId = null,
    ) {}
}
