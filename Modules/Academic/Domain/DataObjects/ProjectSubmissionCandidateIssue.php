<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ProjectSubmissionCandidateIssue
{
    public function __construct(
        public int $learnerProjectId,
        public string $studentName,
        public string $admissionNumber,
        public string $field,
        public string $severity,
        public string $message,
    ) {}
}
