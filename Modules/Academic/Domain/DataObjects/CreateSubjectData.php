<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateSubjectData
{
    public function __construct(
        public int $schoolId,
        public int $frameworkId,
        public string $code,
        public string $name,
        public string $shortName,
        public string $subjectType,
        public int $createdByUserId,
        public ?int $subjectGroupId = null,
        public ?string $zimsecSubjectCode = null,
        public ?string $cambridgeSubjectCode = null,
        public bool $isExaminable = true,
        public bool $hasPracticalComponent = false,
        public bool $hasCoursework = false,
        public ?string $courseworkWeightPercent = null,
        public ?int $defaultPeriodsPerWeek = null,
        public bool $requiresSbp = true,
        public ?int $gradingScaleId = null,
        public ?int $sortOrder = null,
    ) {}
}
