<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class ApproveLeaveRequestData
{
    public function __construct(
        public int $leaveRequestId,
        public int $approvedByUserId,
    ) {}
}
