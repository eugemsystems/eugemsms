<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GrantAwardData
{
    /**
     * @param  array<int, int>|null  $appliesToComponents
     */
    public function __construct(
        public int $schoolId,
        public int $schemeId,
        public int $studentId,
        public int $academicYearId,
        public int $grantedByUserId,
        public string $awardMethod,
        public CarbonInterface $effectiveFrom,
        public ?int $applicationId = null,
        public ?int $termId = null,
        public ?array $appliesToComponents = null,
        public ?string $awardPercent = null,
        public ?int $awardAmountMinor = null,
        public ?string $currency = null,
        public ?int $sponsorGuardianId = null,
        public ?string $conditionNote = null,
    ) {}
}
