<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class MoveLearnerData
{
    public function __construct(
        public int $studentId,
        public int $newBedId,
        public CarbonInterface $effectiveFrom,
        public string $reason,
        public int $movedByUserId,
        public string $allocationType = 'reallocated',
    ) {}
}
