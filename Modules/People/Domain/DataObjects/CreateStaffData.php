<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateStaffData
{
    public function __construct(
        public int $schoolId,
        public string $firstName,
        public string $lastName,
        public CarbonInterface $dateOfBirth,
        public string $gender,
        public string $primaryPhone,
        public string $staffCategory,
        public CarbonInterface $joinedOn,
        public int $createdByUserId,
        public ?int $userId = null,
        public ?string $title = null,
        public ?string $middleNames = null,
        public string $nationality = 'ZW',
        public ?string $nationalRegistrationNo = null,
        public ?string $passportNo = null,
        public ?int $departmentId = null,
        public ?int $postId = null,
        public ?int $reportsToStaffId = null,
        public bool $isTeaching = false,
        public ?string $teacherRegistrationNo = null,
        public ?int $maxWeeklyPeriods = null,
        public bool $overrideEstablishment = false,
        public ?string $overrideReason = null,
    ) {}
}
