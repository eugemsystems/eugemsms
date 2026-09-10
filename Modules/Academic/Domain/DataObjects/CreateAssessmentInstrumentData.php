<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateAssessmentInstrumentData
{
    public function __construct(
        public int $schoolId,
        public int $frameworkId,
        public string $code,
        public string $name,
        public int $projectsPerSubjectPerYear = 1,
        public bool $appliesToExamClasses = false,
        public bool $contributesToFinalMark = true,
        public ?float $defaultWeightPercent = null,
        public bool $isReadonly = false,
        public ?string $referenceCircular = null,
        public string $status = 'active',
    ) {}
}
