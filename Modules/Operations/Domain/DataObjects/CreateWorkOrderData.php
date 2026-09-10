<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateWorkOrderData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public string $workType,
        public string $title,
        public string $description,
        public string $priority,
        public string $assignedTeam,
        public int $costCentreId,
        public string $currency,
        public int $raisedByUserId,
        public ?int $maintenanceAssetId = null,
        public ?int $faultReportId = null,
        public ?int $scheduleId = null,
        public ?string $location = null,
        public ?int $assignedStaffId = null,
        public ?int $contractorSupplierId = null,
        public ?int $budgetLineId = null,
        public ?CarbonInterface $scheduledFor = null,
        public ?CarbonInterface $targetCompletion = null,
    ) {}
}
