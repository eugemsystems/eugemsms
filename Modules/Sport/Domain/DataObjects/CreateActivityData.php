<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

final readonly class CreateActivityData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $activityType,
        public ?string $season = null,
        public string $genderScope = 'both',
        public ?int $minGradeOrdinal = null,
        public ?int $maxGradeOrdinal = null,
        public ?int $coachStaffId = null,
        public ?int $feeComponentId = null,
        public bool $requiresMedicalClearance = false,
        public bool $requiresGuardianConsent = true,
        public ?int $maxParticipants = null,
        public ?int $venueId = null,
    ) {}
}
