<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateLevelSubjectOfferingData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $gradeLevelId,
        public int $subjectId,
        public ?int $pathwayId = null,
        public bool $isCompulsory = false,
        public bool $isAvailable = true,
        public ?int $periodsPerWeek = null,
        public ?int $maxLearners = null,
        public ?string $optionBlock = null,
    ) {}
}
