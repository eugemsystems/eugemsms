<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class RebuildAttendanceSummaryData
{
    public function __construct(
        public int $studentId,
        public int $termId,
        public string $scope = 'term',
        public ?int $subjectId = null,
    ) {}
}
