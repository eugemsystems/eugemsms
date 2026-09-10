<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateLaundryCycleData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $hostelId,
        public CarbonInterface $cycleDate,
        public ?int $supervisedByStaffId = null,
    ) {}
}
