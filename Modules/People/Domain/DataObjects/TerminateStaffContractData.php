<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class TerminateStaffContractData
{
    public function __construct(
        public int $contractId,
        public CarbonInterface $terminatedOn,
        public string $terminationReason,
        public int $terminatedByUserId,
    ) {}
}
