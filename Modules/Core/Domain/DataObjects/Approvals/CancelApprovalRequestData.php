<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Approvals;

final readonly class CancelApprovalRequestData
{
    public function __construct(
        public int $requestId,
        public int $cancelledByUserId,
        public bool $isPrivileged = false,
    ) {}
}
