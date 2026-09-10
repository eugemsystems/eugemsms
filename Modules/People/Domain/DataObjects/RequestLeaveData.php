<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RequestLeaveData
{
    public function __construct(
        public int $schoolId,
        public int $staffId,
        public int $leaveTypeId,
        public int $academicYearId,
        public CarbonInterface $startsOn,
        public CarbonInterface $endsOn,
        public string $workingDays,
        public ?string $reason = null,
        public ?int $supportingDocumentId = null,
        public ?int $coverStaffId = null,
        public ?string $contactWhileAway = null,
        public bool $approveOverdraft = false,
    ) {}
}
