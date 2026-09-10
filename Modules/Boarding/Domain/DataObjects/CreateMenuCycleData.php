<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateMenuCycleData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $name,
        public int $cycleLengthDays = 7,
        public ?CarbonInterface $startsOn = null,
        public ?CarbonInterface $endsOn = null,
    ) {}
}
