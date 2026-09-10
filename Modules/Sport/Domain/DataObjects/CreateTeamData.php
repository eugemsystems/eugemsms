<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

final readonly class CreateTeamData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $activityId,
        public string $name,
        public ?string $ageGroup = null,
        public ?string $level = null,
        public ?int $coachStaffId = null,
        public ?int $captainStudentId = null,
    ) {}
}
