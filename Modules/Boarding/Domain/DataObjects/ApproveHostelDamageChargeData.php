<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class ApproveHostelDamageChargeData
{
    public function __construct(
        public int $damageId,
        public int $feeComponentId,
        public int $actualCostMinor,
        public int $approvedByUserId,
    ) {}
}
