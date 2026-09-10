<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Approvals;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Approvals\Approvable;
use Modules\Core\Domain\DataObjects\Approvals\RejectStepData;
use Modules\Core\Domain\Events\Approvals\ApprovalRejected;
use Modules\Core\Domain\Exceptions\ApprovalNotPendingException;
use Modules\Core\Domain\Exceptions\ApproverNotAuthorizedException;
use Modules\Core\Domain\Exceptions\CommentRequiredException;
use Modules\Core\Domain\Exceptions\SelfApprovalNotPermittedException;
use Modules\Core\Domain\Exceptions\StepActionNotPermittedException;
use Modules\Core\Domain\Support\Approvals\ApproverResolver;
use Modules\Core\Domain\Support\Approvals\DelegationResolver;
use Modules\Core\Models\ApprovalAction as ApprovalActionRecord;
use Modules\Core\Models\ApprovalRequest;

/**
 * ACT-RejectStep (Book A CORE-07 BR-CORE-07-006/AC-CORE-07-003).
 * Rejection terminates the request immediately — later steps are
 * never consulted, and `onRejected` fires on the approvable, not
 * `onApproved`.
 */
final class RejectStepAction extends Action
{
    public function __construct(
        private readonly ApproverResolver $approverResolver,
        private readonly DelegationResolver $delegationResolver,
    ) {}

    public function execute(RejectStepData $data): ApprovalRequest
    {
        $request = ApprovalRequest::withoutGlobalScopes()->findOrFail($data->requestId);

        if (! $request->isPending()) {
            throw new ApprovalNotPendingException("This request is [{$request->status}] and cannot be acted on.");
        }

        if ($data->actorUserId === $request->requested_by) {
            throw new SelfApprovalNotPermittedException('You cannot reject your own request.');
        }

        $step = $request->currentStep();

        if (! $step->can_reject) {
            throw new StepActionNotPermittedException('This step does not permit rejection.');
        }

        /** @var Approvable $approvable */
        $approvable = $request->resolveApprovable();
        $resolvedApprovers = $this->approverResolver->resolve($step, $request->school_id, $approvable);

        $onBehalfOfId = in_array($data->actorUserId, $resolvedApprovers, true)
            ? null
            : $this->delegationResolver->delegatorFor($data->actorUserId, $request->school_id, $approvable->approvableType(), $resolvedApprovers);

        if (! in_array($data->actorUserId, $resolvedApprovers, true) && $onBehalfOfId === null) {
            throw new ApproverNotAuthorizedException('You are not an approver for this step.');
        }

        if ($step->requires_comment && trim((string) $data->comment) === '') {
            throw new CommentRequiredException('This step requires a comment.');
        }

        return $this->transaction(function () use ($request, $step, $data, $onBehalfOfId, $approvable): ApprovalRequest {
            ApprovalActionRecord::create([
                'request_id' => $request->id,
                'step_number' => $step->step_number,
                'action' => 'rejected',
                'actor_id' => $data->actorUserId,
                'on_behalf_of_id' => $onBehalfOfId,
                'comment' => $data->comment,
                'ip_address' => $data->ip,
                'acted_at' => Carbon::now(),
            ]);

            $request->forceFill(['status' => 'rejected', 'completed_at' => Carbon::now()])->save();
            $approvable->onRejected($request);

            event(new ApprovalRejected($request));

            return $request;
        });
    }
}
