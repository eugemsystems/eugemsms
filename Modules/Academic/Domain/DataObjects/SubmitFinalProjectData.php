<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SubmitFinalProjectData
{
    public function __construct(
        public int $learnerProjectId,
        public string $evidenceType,
        public int $uploadedBy,
        public ?int $fileId = null,
        public ?string $externalUrl = null,
        public ?string $caption = null,
    ) {}
}
