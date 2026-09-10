<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class RaiseComplaintData
{
    public function __construct(
        public int $schoolId,
        public int $categoryId,
        public string $raisedByType,
        public string $subject,
        public string $description,
        public ?int $raisedById = null,
        public ?int $relatedStudentId = null,
        public string $severity = 'medium',
        public bool $suspectedSafeguardingConcern = false,
        public ?int $reporterUserId = null,
    ) {}
}
