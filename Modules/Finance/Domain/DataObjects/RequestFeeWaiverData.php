<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class RequestFeeWaiverData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $studentId,
        public string $type,
        public int $amountMinor,
        public string $currency,
        public string $reasonCode,
        public string $reason,
        public int $requestedByUserId,
        public ?int $invoiceId = null,
    ) {}
}
