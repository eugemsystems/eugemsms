<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateExamSlotPlanData
{
    /**
     * @param  array<int, int>  $affectedLevels
     * @param  array<int, int>|null  $venuesReserved
     * @param  array<int, int>|null  $staffReserved
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public string $name,
        public string $examBody,
        public CarbonInterface $startsOn,
        public CarbonInterface $endsOn,
        public array $affectedLevels,
        public int $createdByUserId,
        public ?array $venuesReserved = null,
        public ?array $staffReserved = null,
    ) {}
}
