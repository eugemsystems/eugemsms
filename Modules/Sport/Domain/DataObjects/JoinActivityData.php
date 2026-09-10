<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class JoinActivityData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $activityId,
        public int $studentId,
        public int $raisedByUserId,
        public bool $consentReceived = false,
        public ?bool $medicalCleared = null,
        public ?string $role = null,
        public ?CarbonInterface $joinedOn = null,
        public bool $overrideCapacity = false,
        public ?string $overrideReason = null,
        public ?int $fullTermFeeMinor = null,
        public ?string $feeCurrency = null,
    ) {}
}
