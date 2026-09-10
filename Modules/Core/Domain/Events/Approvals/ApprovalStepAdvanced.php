<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Approvals;

use Modules\Core\Models\ApprovalRequest;

/**
 * BR-CORE-07-015 — every step transition notifies the next approver
 * and the requester (through CORE-09, not built yet).
 */
final class ApprovalStepAdvanced
{
    public function __construct(
        public readonly ApprovalRequest $request,
    ) {}
}
