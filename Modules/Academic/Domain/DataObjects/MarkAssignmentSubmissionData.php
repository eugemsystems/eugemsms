<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class MarkAssignmentSubmissionData
{
    public function __construct(
        public int $submissionId,
        public float $rawMark,
        public int $markedByUserId,
        public ?string $feedback = null,
    ) {}
}
