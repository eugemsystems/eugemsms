<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class RefundPaymentData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $studentId,
        public int $creditBalanceAccountId,
        public int $bankAccountGlId,
        public int $amountMinor,
        public string $currency,
        public string $reason,
        public int $requestedByUserId,
        public int $approvedByUserId,
        public ?int $gatewayId = null,
    ) {}
}
