<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class RejectFeeWaiverData
{
    public function __construct(
        public int $feeWaiverId,
        public int $rejectedByUserId,
    ) {}
}
