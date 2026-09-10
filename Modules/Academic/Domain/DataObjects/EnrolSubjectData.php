<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class EnrolSubjectData
{
    public function __construct(
        public int $studentId,
        public int $subjectId,
        public int $termId,
        public int $addedByUserId,
        public ?int $classId = null,
        public string $enrolmentReason = 'elective',
        public ?CarbonInterface $effectiveFrom = null,
        public bool $acknowledgeWarnings = false,
        public ?string $reason = null,
    ) {}
}
