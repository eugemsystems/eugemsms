<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CommitBillingRunData
{
    public function __construct(
        public int $billingRunId,
        public int $committedByUserId,
        public CarbonInterface $effectiveAt,
    ) {}
}
