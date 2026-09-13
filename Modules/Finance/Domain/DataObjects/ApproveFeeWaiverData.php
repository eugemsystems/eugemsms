<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class ApproveFeeWaiverData
{
    public function __construct(
        public int $feeWaiverId,
        public int $approvedByUserId,
        public int $contraAccountId,
        public int $debtorAccountId,
    ) {}
}
