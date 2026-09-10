<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class SwapDutyAssignmentData
{
    public function __construct(
        public int $assignmentId,
        public int $newStaffId,
        public int $approvedByUserId,
        public bool $bothPartiesConsented,
    ) {}
}
