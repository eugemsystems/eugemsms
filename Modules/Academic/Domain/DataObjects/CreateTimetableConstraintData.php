<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateTimetableConstraintData
{
    /**
     * @param  array<int, int>|null  $cycleDays
     * @param  array<int, int>|null  $periodNumbers
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $constraintType,
        public string $severity,
        public int $weight = 1,
        public ?int $subjectId = null,
        public ?int $staffId = null,
        public ?int $classId = null,
        public ?int $venueId = null,
        public ?int $gradeLevelId = null,
        public ?array $cycleDays = null,
        public ?array $periodNumbers = null,
        public ?int $value = null,
        public ?string $reason = null,
    ) {}
}
