<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ScheduleFixtureData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $teamId,
        public string $opponent,
        public string $fixtureType,
        public string $venueType,
        public CarbonInterface $fixtureDate,
        public ?int $venueId = null,
        public ?string $venueName = null,
        public ?string $startTime = null,
        public ?string $departureTime = null,
        public ?string $returnTime = null,
    ) {}
}
