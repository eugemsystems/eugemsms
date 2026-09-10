<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ReportHostelDamageData
{
    /**
     * @param  array<int, int>|null  $liableStudentIds
     * @param  array<int, int>|null  $photoFileIds
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $hostelId,
        public string $damageType,
        public string $description,
        public string $liability,
        public string $currency,
        public int $reportedByUserId,
        public CarbonInterface $reportedAt,
        public ?int $roomId = null,
        public ?int $bedId = null,
        public ?int $inspectionId = null,
        public ?array $liableStudentIds = null,
        public ?int $estimatedCostMinor = null,
        public ?array $photoFileIds = null,
    ) {}
}
