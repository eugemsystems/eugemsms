<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RequestVirementData
{
    public function __construct(
        public int $budgetId,
        public int $fromLineId,
        public int $toLineId,
        public int $amountMinor,
        public string $reason,
        public int $requestedByUserId,
        public CarbonInterface $effectiveFrom,
    ) {}
}
