<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class IssueItemToLearnerData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $studentId,
        public int $issuableItemId,
        public string $conditionAtIssue,
        public int $issuedByUserId,
        public CarbonInterface $issuedOn,
        public int $quantity = 1,
        public ?string $tagReference = null,
        public ?string $notes = null,
    ) {}
}
