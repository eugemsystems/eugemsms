<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Approvals;

final readonly class ResubmitApprovalData
{
    public function __construct(
        public int $requestId,
    ) {}
}
