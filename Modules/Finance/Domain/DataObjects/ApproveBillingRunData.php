<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class ApproveBillingRunData
{
    public function __construct(
        public int $billingRunId,
        public int $approvedByUserId,
    ) {}
}
