<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Approvals;

use Modules\Core\Models\ApprovalRequest;

/**
 * BR-CORE-07-008 — notifies the escalation role but does not transfer
 * approval authority.
 */
final class ApprovalEscalated
{
    public function __construct(
        public readonly ApprovalRequest $request,
    ) {}
}
