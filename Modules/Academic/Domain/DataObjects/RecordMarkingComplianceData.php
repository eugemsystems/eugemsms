<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordMarkingComplianceData
{
    public function __construct(
        public int $staffId,
        public int $termId,
        public CarbonInterface $sessionDate,
    ) {}
}
