<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateHouseCompetitionData
{
    /**
     * @param  array<int, int>  $pointsScheme  place => points, e.g. [1 => 10, 2 => 7]
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $name,
        public string $competitionType,
        public array $pointsScheme,
        public ?CarbonInterface $heldOn = null,
        public string $weight = '1',
    ) {}
}
