<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class BulkEnrolSubjectsData
{
    /**
     * @param  array<int, int>  $studentIds
     */
    public function __construct(
        public array $studentIds,
        public int $subjectId,
        public int $termId,
        public int $addedByUserId,
        public string $enrolmentReason = 'elective',
        public ?CarbonInterface $effectiveFrom = null,
        public bool $acknowledgeWarnings = false,
        public ?string $reason = null,
    ) {}
}
