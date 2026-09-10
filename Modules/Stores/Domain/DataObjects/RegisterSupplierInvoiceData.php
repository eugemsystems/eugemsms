<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RegisterSupplierInvoiceData
{
    /**
     * @param  array<int, array{poLineId: ?int, grnLineId: ?int, description: string, quantity: float, unitPriceMinor: int, taxCategory: string, taxRatePercent: float, expenseAccountId: int, costCentreId: ?int}>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $supplierId,
        public string $invoiceNumber,
        public CarbonInterface $invoiceDate,
        public CarbonInterface $receivedOn,
        public CarbonInterface $dueDate,
        public string $currency,
        public array $lines,
        public int $registeredByUserId,
        public ?int $purchaseOrderId = null,
        public bool $isFiscalInvoice = false,
        public ?string $fiscalDeviceId = null,
        public ?string $fiscalVerificationCode = null,
    ) {}
}
