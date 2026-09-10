<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

final readonly class CreateSanctionTypeData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public int $severityLevel,
        public bool $requiresGuardianMeeting = false,
        public bool $requiresCommittee = false,
        public bool $removesFromLessons = false,
        public bool $removesFromCampus = false,
        public ?int $maxDurationDays = null,
        public bool $appealable = true,
        public int $appealWindowDays = 5,
    ) {}
}
