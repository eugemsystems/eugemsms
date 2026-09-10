<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class EndBedAllocationData
{
    public function __construct(
        public int $studentId,
        public CarbonInterface $effectiveTo,
        public string $reason,
    ) {}
}
