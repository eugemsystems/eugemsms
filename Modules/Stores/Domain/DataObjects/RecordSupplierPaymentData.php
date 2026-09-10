<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordSupplierPaymentData
{
    /**
     * @param  array<int, int>  $invoiceIds
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $supplierId,
        public array $invoiceIds,
        public CarbonInterface $paymentDate,
        public string $paymentMethod,
        public int $bankAccountId,
        public int $approvedByUserId,
        public ?int $withholdingPayableAccountId = null,
        public ?string $reference = null,
        public ?int $batchId = null,
    ) {}
}
