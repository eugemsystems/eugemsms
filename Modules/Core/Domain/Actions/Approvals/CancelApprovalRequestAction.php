<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Approvals;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Approvals\CancelApprovalRequestData;
use Modules\Core\Domain\Events\Approvals\ApprovalCancelled;
use Modules\Core\Domain\Exceptions\ApprovalNotPendingException;
use Modules\Core\Domain\Exceptions\AuthorisationException;
use Modules\Core\Models\ApprovalAction as ApprovalActionRecord;
use Modules\Core\Models\ApprovalRequest;

/**
 * ACT-CancelApprovalRequest (Book A CORE-07 BR-CORE-07-012). Only the
 * requester or a `core.approval.cancel` holder may cancel, and only
 * while pending — `$data->isPrivileged` is the caller's own
 * permission check result, per the Action base class's rule that an
 * Action re-checks authorisation itself but never reads request()/
 * session()/auth() to do it.
 */
final class CancelApprovalRequestAction extends Action
{
    public function execute(CancelApprovalRequestData $data): ApprovalRequest
    {
        $request = ApprovalRequest::withoutGlobalScopes()->findOrFail($data->requestId);

        if (! $request->isPending()) {
            throw new ApprovalNotPendingException("This request is [{$request->status}] and cannot be cancelled.");
        }

        if ($data->cancelledByUserId !== $request->requested_by && ! $data->isPrivileged) {
            throw new class('Only the requester or a user with core.approval.cancel may cancel this request.') extends AuthorisationException
            {
                public function errorCode(): string
                {
                    return 'CANCEL_NOT_PERMITTED';
                }
            };
        }

        return $this->transaction(function () use ($request, $data): ApprovalRequest {
            ApprovalActionRecord::create([
                'request_id' => $request->id,
                'step_number' => $request->current_step,
                'action' => 'cancelled',
                'actor_id' => $data->cancelledByUserId,
                'acted_at' => Carbon::now(),
            ]);

            $request->forceFill(['status' => 'cancelled', 'completed_at' => Carbon::now()])->save();

            event(new ApprovalCancelled($request));

            return $request;
        });
    }
}
