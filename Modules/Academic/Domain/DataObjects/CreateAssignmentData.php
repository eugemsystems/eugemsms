<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateAssignmentData
{
    /**
     * @param  array<int, int>|null  $attachmentFileIds
     */
    public function __construct(
        public int $courseSpaceId,
        public string $title,
        public string $instructions,
        public CarbonInterface $opensAt,
        public CarbonInterface $dueAt,
        public string $latePolicy,
        public string $submissionType,
        public int $createdByUserId,
        public ?array $attachmentFileIds = null,
        public ?float $maxMark = null,
        public ?int $rubricId = null,
        public ?int $assessmentTypeId = null,
        public ?float $latePenaltyPercentPerDay = null,
        public bool $allowsResubmission = false,
    ) {}
}
