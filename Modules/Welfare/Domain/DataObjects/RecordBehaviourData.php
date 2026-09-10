<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordBehaviourData
{
    /**
     * @param  array<int, string>|null  $witnesses
     * @param  array<int, int>|null  $otherLearnersInvolved
     * @param  array<int, int>|null  $evidenceFileIds
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $studentId,
        public int $categoryId,
        public string $description,
        public CarbonInterface $occurredAt,
        public int $reportedByUserId,
        public ?string $location = null,
        public ?string $context = null,
        public ?int $subjectId = null,
        public ?int $classId = null,
        public ?int $hostelId = null,
        public ?array $witnesses = null,
        public ?array $otherLearnersInvolved = null,
        public ?array $evidenceFileIds = null,
        public ?int $pointsOverride = null,
    ) {}
}
