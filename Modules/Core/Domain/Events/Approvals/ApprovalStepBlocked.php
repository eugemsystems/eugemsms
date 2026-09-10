<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Approvals;

use Modules\Core\Models\ApprovalRequest;

/**
 * BR-CORE-07-003/AC-CORE-07-004 — a step has no resolvable approver.
 * The request stays pending; an administrator is notified via this
 * event, never auto-approved.
 */
final class ApprovalStepBlocked
{
    public function __construct(
        public readonly ApprovalRequest $request,
    ) {}
}
