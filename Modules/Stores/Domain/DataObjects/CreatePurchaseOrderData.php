<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreatePurchaseOrderData
{
    /**
     * @param  array<int, array{itemId: ?int, description: string, quantityOrdered: float, unit: string, unitPriceMinor: int, taxRatePercent: float, taxCategory: string, expenseAccountId: ?int, isCapital: bool, storeId: ?int}>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $supplierId,
        public int $costCentreId,
        public CarbonInterface $orderDate,
        public string $currency,
        public array $lines,
        public int $createdByUserId,
        public ?int $requisitionId = null,
        public ?int $quotationId = null,
        public ?CarbonInterface $expectedDelivery = null,
        public ?int $budgetLineId = null,
    ) {}
}
