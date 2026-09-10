<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateProjectBriefData
{
    /**
     * @param  array<int, string>|null  $learningObjectives
     * @param  array<int, string>  $deliverables
     * @param  array<int, string>|null  $resources
     * @param  array<int, ProjectMilestoneInput>  $milestones
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $instrumentId,
        public int $subjectId,
        public int $gradeLevelId,
        public string $title,
        public string $description,
        public array $deliverables,
        public CarbonInterface $startsOn,
        public CarbonInterface $dueOn,
        public float $maxMark,
        public int $rubricId,
        public int $createdBy,
        public array $milestones = [],
        public ?array $learningObjectives = null,
        public ?string $heritageLink = null,
        public ?array $resources = null,
        public ?int $briefDocumentId = null,
        public bool $overrideProjectLimit = false,
        public ?string $overrideReason = null,
    ) {}
}
