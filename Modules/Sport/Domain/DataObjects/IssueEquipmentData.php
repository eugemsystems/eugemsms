<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class IssueEquipmentData
{
    public function __construct(
        public int $schoolId,
        public int $activityId,
        public int $assetId,
        public int $studentId,
        public int $issuedByUserId,
        public ?CarbonInterface $expectedReturnOn = null,
        public ?string $notes = null,
    ) {}
}
