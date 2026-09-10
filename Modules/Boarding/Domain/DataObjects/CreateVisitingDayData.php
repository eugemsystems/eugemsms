<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateVisitingDayData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public CarbonInterface $visitDate,
        public string $name,
        public string $startsAt,
        public string $endsAt,
        public ?int $slotDurationMinutes = null,
        public ?int $maxPerSlot = null,
    ) {}
}
