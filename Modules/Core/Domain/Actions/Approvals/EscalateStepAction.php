<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Approvals;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Approvals\EscalateStepData;
use Modules\Core\Domain\Events\Approvals\ApprovalEscalated;
use Modules\Core\Domain\Exceptions\ApprovalNotPendingException;
use Modules\Core\Models\ApprovalRequest;

/**
 * ACT-EscalateStep (Book A CORE-07 BR-CORE-07-008). Notifies the
 * escalation role but never transfers authority — no approver
 * resolution changes here, only the event a notification listener
 * (CORE-09, not built yet) would act on. The scheduled trigger that
 * calls this once `escalate_after_hours` elapses is a later wave's
 * job, same as every other CORE background job in Book A.
 */
final class EscalateStepAction extends Action
{
    public function execute(EscalateStepData $data): ApprovalRequest
    {
        $request = ApprovalRequest::withoutGlobalScopes()->findOrFail($data->requestId);

        if (! $request->isPending()) {
            throw new ApprovalNotPendingException("This request is [{$request->status}] and cannot be escalated.");
        }

        event(new ApprovalEscalated($request));

        return $request;
    }
}
