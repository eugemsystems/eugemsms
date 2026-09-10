<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Modules\Finance\Domain\Support\AllocationStrategy;

final readonly class ClearChequeData
{
    public function __construct(
        public int $receiptTenderId,
        public int $clearedByUserId,
        public int $unclearedChequeAccountId,
        public ?AllocationStrategy $allocationStrategy = null,
    ) {}
}
