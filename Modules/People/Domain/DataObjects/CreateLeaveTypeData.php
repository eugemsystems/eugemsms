<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class CreateLeaveTypeData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $accrualMethod,
        public ?string $annualEntitlementDays = null,
        public bool $isPaid = true,
        public bool $requiresDocument = false,
        public ?int $maxConsecutiveDays = null,
        public ?string $carryForwardDays = null,
        public bool $requiresCover = true,
    ) {}
}
