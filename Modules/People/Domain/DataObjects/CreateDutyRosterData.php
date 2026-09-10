<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class CreateDutyRosterData
{
    /**
     * @param  array<int, string>|null  $eligibleCategories
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public string $dutyType,
        public string $name,
        public string $rotationPattern,
        public ?array $eligibleCategories = null,
    ) {}
}
