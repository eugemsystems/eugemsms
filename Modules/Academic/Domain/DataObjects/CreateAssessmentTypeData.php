<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateAssessmentTypeData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $category,
        public float $defaultWeightPercent,
        public bool $appearsOnReportCard = true,
        public bool $isExamination = false,
        public ?int $sortOrder = null,
    ) {}
}
