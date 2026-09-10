<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

use Carbon\CarbonInterface;

final readonly class CreateTermData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $number,
        public string $name,
        public CarbonInterface $startsOn,
        public CarbonInterface $endsOn,
        public ?CarbonInterface $halfTermStartsOn = null,
        public ?CarbonInterface $halfTermEndsOn = null,
        public ?CarbonInterface $feeDueOn = null,
        public ?CarbonInterface $resultsDueOn = null,
        public ?CarbonInterface $reportsReleaseOn = null,
        public ?int $actingUserId = null,
    ) {}
}
