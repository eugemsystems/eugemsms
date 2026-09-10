<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SubmitMilestoneEvidenceData
{
    public function __construct(
        public int $learnerProjectId,
        public int $milestoneId,
        public string $evidenceType,
        public int $uploadedBy,
        public ?int $fileId = null,
        public ?string $externalUrl = null,
        public ?string $caption = null,
    ) {}
}
