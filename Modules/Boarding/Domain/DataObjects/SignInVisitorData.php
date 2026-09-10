<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class SignInVisitorData
{
    public function __construct(
        public int $schoolId,
        public string $fullName,
        public string $visitPurpose,
        public int $gateStaffUserId,
        public ?string $idType = null,
        public ?string $idNumber = null,
        public ?string $phone = null,
        public ?int $hostStaffId = null,
        public ?int $studentId = null,
        public ?string $vehicleRegistration = null,
        public ?string $badgeNumber = null,
        public ?int $expectedDurationMins = null,
        public bool $inductionCompleted = false,
    ) {}
}
