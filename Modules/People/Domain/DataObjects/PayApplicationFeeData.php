<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class PayApplicationFeeData
{
    public function __construct(
        public int $applicationId,
        public int $termId,
        public string $tenderType,
        public int $bankAccountId,
        public int $incomeAccountId,
        public int $receivedByUserId,
    ) {}
}
