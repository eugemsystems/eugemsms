<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SubmitAssignmentData
{
    /**
     * @param  array<int, int>|null  $fileIds
     */
    public function __construct(
        public int $assignmentId,
        public int $studentId,
        public ?string $submittedText = null,
        public ?string $submittedLink = null,
        public ?array $fileIds = null,
    ) {}
}
