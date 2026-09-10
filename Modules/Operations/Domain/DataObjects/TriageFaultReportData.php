<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\DataObjects;

use Carbon\CarbonInterface;

/**
 * The work-order-specific facts triage supplies on top of what the
 * originating `FaultReport` already carries (location, description,
 * maintenance asset) — Book H2 OPS-02 §6/BR-OPS-02-003.
 */
final readonly class TriageFaultReportData
{
    public function __construct(
        public int $academicYearId,
        public string $workType,
        public string $title,
        public string $priority,
        public string $assignedTeam,
        public int $costCentreId,
        public string $currency,
        public int $triagedByUserId,
        public ?int $assignedStaffId = null,
        public ?int $contractorSupplierId = null,
        public ?int $budgetLineId = null,
        public ?CarbonInterface $scheduledFor = null,
        public ?CarbonInterface $targetCompletion = null,
        public ?int $estimatedCostMinor = null,
    ) {}
}
