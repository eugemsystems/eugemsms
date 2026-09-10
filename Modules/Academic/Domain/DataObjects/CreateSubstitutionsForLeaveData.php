<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateSubstitutionsForLeaveData
{
    public function __construct(
        public int $staffId,
        public CarbonInterface $fromDate,
        public CarbonInterface $toDate,
        public ?int $leaveRequestId = null,
        public string $reason = 'leave',
    ) {}
}
