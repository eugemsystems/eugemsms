<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;
use Modules\Finance\Domain\Support\AllocationStrategy;

final readonly class CreateReceiptData
{
    /**
     * @param  array<int, array{tender_type: string, amount_minor: int, currency: string, reference?: string|null, bank_account_id?: int|null, is_cleared?: bool}>  $tenders
     * @param  array<int, int>|null  $manualInvoiceOrder
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public string $receiptType,
        public string $payerType,
        public string $payerName,
        public string $currency,
        public array $tenders,
        public int $receivedByUserId,
        public ?int $tillSessionId = null,
        public ?int $studentId = null,
        public ?int $payerId = null,
        public ?string $payerPhone = null,
        public ?string $narration = null,
        public ?AllocationStrategy $allocationStrategy = null,
        public ?array $manualInvoiceOrder = null,
        public ?int $creditBalanceAccountId = null,
        public ?int $suspenseAccountId = null,
        public ?int $unclearedChequeAccountId = null,
        public ?CarbonInterface $effectiveDate = null,
        public ?int $gatewayFeeMinor = null,
        public ?int $gatewayFeeExpenseAccountId = null,
    ) {}
}
