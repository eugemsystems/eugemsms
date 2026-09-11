<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class DecideScholarshipApplicationData
{
    public function __construct(
        public int $applicationId,
        public string $status,
        public int $decidedByUserId,
        public ?string $committeeNotes = null,
        public ?string $rejectionReason = null,
    ) {}
}
