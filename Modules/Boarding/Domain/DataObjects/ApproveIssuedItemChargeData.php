<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class ApproveIssuedItemChargeData
{
    public function __construct(
        public int $learnerIssuedItemId,
        public int $feeComponentId,
        public int $approvedByUserId,
        public ?int $chargeAmountMinor = null,
    ) {}
}
