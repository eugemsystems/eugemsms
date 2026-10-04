<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateLearnerIncompatibilityData
{
    public function __construct(
        public int $schoolId,
        public int $studentAId,
        public int $studentBId,
        public string $scope,
        public string $reasonCategory,
        public int $raisedByUserId,
        public ?string $reason = null,
        public bool $isConfidential = true,
        public ?CarbonInterface $expiresOn = null,
    ) {}
}
