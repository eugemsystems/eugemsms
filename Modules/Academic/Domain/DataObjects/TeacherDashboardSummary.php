<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class TeacherDashboardSummary
{
    /**
     * @param  array<string, int>  $observationRatingCounts
     */
    public function __construct(
        public float $coveragePercent,
        public float $lessonPlanSubmissionRate,
        public array $observationRatingCounts,
        public int $lessonPlanCount,
        public int $observationCount,
    ) {}
}
