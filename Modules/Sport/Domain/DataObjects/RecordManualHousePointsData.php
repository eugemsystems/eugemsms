<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

final readonly class RecordManualHousePointsData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $houseId,
        public float $points,
        public int $awardedByUserId,
        public ?string $reason = null,
    ) {}
}
