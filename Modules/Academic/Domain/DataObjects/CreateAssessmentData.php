<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateAssessmentData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $assessmentTypeId,
        public int $subjectId,
        public string $title,
        public float $maxMark,
        public float $weightPercent,
        public int $createdByUserId,
        public ?int $gradeLevelId = null,
        public ?int $classId = null,
        public ?int $teachingGroupId = null,
        public ?CarbonInterface $assessedOn = null,
        public ?int $gradingScaleId = null,
    ) {}
}
