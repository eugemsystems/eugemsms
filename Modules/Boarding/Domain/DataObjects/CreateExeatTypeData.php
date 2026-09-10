<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateExeatTypeData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public ?int $maxDurationHours = null,
        public bool $requiresGuardianRequest = true,
        public bool $requiresDocument = false,
        public int $minNoticeHours = 24,
        public ?int $allowedPerTerm = null,
        public bool $countsTowardQuota = true,
        public bool $blocksOnFeeArrears = false,
        public bool $blocksOnSuspension = true,
    ) {}
}
