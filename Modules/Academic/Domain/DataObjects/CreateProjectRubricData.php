<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateProjectRubricData
{
    /**
     * @param  array<int, RubricCriterionInput>  $criteria
     */
    public function __construct(
        public int $schoolId,
        public string $name,
        public array $criteria,
        public float $totalMark = 100.0,
        public ?int $subjectId = null,
        public bool $isTemplate = false,
        public bool $isActive = true,
    ) {}
}
