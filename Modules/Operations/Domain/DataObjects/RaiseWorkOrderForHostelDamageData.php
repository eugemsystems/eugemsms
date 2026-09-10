<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\DataObjects;

final readonly class RaiseWorkOrderForHostelDamageData
{
    public function __construct(
        public int $hostelDamageId,
        public int $academicYearId,
        public string $workType,
        public string $priority,
        public string $assignedTeam,
        public int $costCentreId,
        public int $raisedByUserId,
        public ?int $assignedStaffId = null,
        public ?int $contractorSupplierId = null,
        public ?int $budgetLineId = null,
    ) {}
}
