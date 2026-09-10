<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class RecomputeSubjectPositionsData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $subjectId,
    ) {}
}
