<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ApproveProjectBriefData
{
    public function __construct(
        public int $briefId,
        public int $approvedByStaffId,
    ) {}
}
