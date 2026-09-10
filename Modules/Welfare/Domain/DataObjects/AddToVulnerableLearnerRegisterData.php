<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AddToVulnerableLearnerRegisterData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $vulnerabilityType,
        public CarbonInterface $identifiedAt,
        public int $identifiedByUserId,
        public ?string $supportPlan = null,
        public ?int $assignedMentorId = null,
        public int $reviewFrequencyDays = 30,
    ) {}
}
