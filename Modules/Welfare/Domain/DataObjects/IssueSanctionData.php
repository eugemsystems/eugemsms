<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class IssueSanctionData
{
    /**
     * @param  array<int, int>  $behaviourRecordIds
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $studentId,
        public int $sanctionTypeId,
        public array $behaviourRecordIds,
        public string $reason,
        public CarbonInterface $startsOn,
        public int $issuedByUserId,
        public ?CarbonInterface $endsOn = null,
        public ?int $durationDays = null,
        public ?int $committeeRecordId = null,
        public ?string $boardingArrangements = null,
    ) {}
}
