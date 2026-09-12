<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Approvals;

use Modules\Core\Domain\Contracts\Approvals\Approvable;
use Modules\Core\Models\ApprovalRequest;

/**
 * Book A CORE-07 §5/§6. "My approvals" has no `approver_id` column to
 * query directly — eligibility is resolved per pending request from
 * its current step (BR-CORE-07-002/009), the same way
 * `ApproveStepAction`/`RejectStepAction`/`ReturnStepAction` each work
 * it out inline before acting. Pulled out here so the read-only
 * screens (`Approvals\Queue`'s list, `Approvals\Show`'s "can I act on
 * this") and those write-side Actions share one answer instead of two
 * independently-maintained ones.
 */
final class ApprovalEligibilityChecker
{
    public function __construct(
        private readonly ApproverResolver $approverResolver,
        private readonly DelegationResolver $delegationResolver,
    ) {}

    public function canActOn(ApprovalRequest $request, int $userId): bool
    {
        if (! $request->isPending() || $request->requested_by === $userId) {
            return false;
        }

        $step = $request->currentStep();

        if ($step === null) {
            return false;
        }

        $approvable = $request->resolveApprovable();

        if (! $approvable instanceof Approvable) {
            return false;
        }

        $resolved = $this->approverResolver->resolve($step, $request->school_id, $approvable);

        if (in_array($userId, $resolved, true)) {
            return true;
        }

        return $this->delegationResolver->delegatorFor($userId, $request->school_id, $approvable->approvableType(), $resolved) !== null;
    }
}
