<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ComputeTermSubjectResultsData
{
    public function __construct(
        public int $studentId,
        public int $subjectId,
        public int $academicYearId,
        public int $termId,
    ) {}
}
