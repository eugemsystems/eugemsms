<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateAlumniEventData
{
    /**
     * @param  array<int, int>|null  $targetGraduationYears
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $title,
        public CarbonInterface $startsAt,
        public string $eventType,
        public ?array $targetGraduationYears = null,
        public bool $requiresTicket = false,
        public ?string $description = null,
        public ?string $location = null,
    ) {}
}
