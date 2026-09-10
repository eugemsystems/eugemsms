<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Approvals;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Approvals\Approvable;
use Modules\Core\Domain\DataObjects\Approvals\ApproveStepData;
use Modules\Core\Domain\Events\Approvals\ApprovalApproved;
use Modules\Core\Domain\Events\Approvals\ApprovalStepAdvanced;
use Modules\Core\Domain\Events\Approvals\ApprovalStepBlocked;
use Modules\Core\Domain\Exceptions\ApprovalNotPendingException;
use Modules\Core\Domain\Exceptions\ApproverNotAuthorizedException;
use Modules\Core\Domain\Exceptions\CommentRequiredException;
use Modules\Core\Domain\Exceptions\SelfApprovalNotPermittedException;
use Modules\Core\Domain\Support\Approvals\ApproverResolver;
use Modules\Core\Domain\Support\Approvals\DelegationResolver;
use Modules\Core\Models\ApprovalAction as ApprovalActionRecord;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\ApprovalStep;

/**
 * ACT-ApproveStep (Book A CORE-07 §3/BR-CORE-07-004/005/009/014). The
 * densest Action in this module: self-approval and comment-required
 * gates, delegated-identity resolution, then sequential/parallel_all/
 * parallel_any counting to decide whether the step (and, if it was the
 * last one, the whole request) is now satisfied.
 */
final class ApproveStepAction extends Action
{
    public function __construct(
        private readonly ApproverResolver $approverResolver,
        private readonly DelegationResolver $delegationResolver,
    ) {}

    public function execute(ApproveStepData $data): ApprovalRequest
    {
        $request = ApprovalRequest::withoutGlobalScopes()->findOrFail($data->requestId);

        if (! $request->isPending()) {
            throw new ApprovalNotPendingException("This request is [{$request->status}] and cannot be acted on.");
        }

        if ($data->actorUserId === $request->requested_by) {
            throw new SelfApprovalNotPermittedException('You cannot approve your own request.');
        }

        $step = $request->currentStep();
        /** @var Approvable $approvable */
        $approvable = $request->resolveApprovable();
        $resolvedApprovers = $this->approverResolver->resolve($step, $request->school_id, $approvable);

        $onBehalfOfId = $this->authoriseActor($data->actorUserId, $resolvedApprovers, $request->school_id, $approvable->approvableType());

        if ($step->requires_comment && trim((string) $data->comment) === '') {
            throw new CommentRequiredException('This step requires a comment.');
        }

        return $this->transaction(function () use ($request, $step, $data, $onBehalfOfId, $resolvedApprovers, $approvable): ApprovalRequest {
            ApprovalActionRecord::create([
                'request_id' => $request->id,
                'step_number' => $step->step_number,
                'action' => 'approved',
                'actor_id' => $data->actorUserId,
                'on_behalf_of_id' => $onBehalfOfId,
                'comment' => $data->comment,
                'ip_address' => $data->ip,
                'acted_at' => Carbon::now(),
            ]);

            if (! $this->stepSatisfied($request, $step, $resolvedApprovers)) {
                return $request;
            }

            $lastStepNumber = (int) ApprovalStep::where('chain_id', $step->chain_id)->max('step_number');

            if ($step->step_number >= $lastStepNumber) {
                $request->forceFill(['status' => 'approved', 'completed_at' => Carbon::now()])->save();
                $approvable->onApproved($request);
                event(new ApprovalApproved($request));

                return $request;
            }

            $request->forceFill(['current_step' => $step->step_number + 1])->save();
            event(new ApprovalStepAdvanced($request));

            $nextStep = $request->currentStep();

            if ($nextStep !== null && $this->approverResolver->resolve($nextStep, $request->school_id, $approvable) === []) {
                event(new ApprovalStepBlocked($request));
            }

            return $request;
        });
    }

    /**
     * @param  array<int, int>  $resolvedApprovers
     */
    private function authoriseActor(int $actorId, array $resolvedApprovers, int $schoolId, string $approvableType): ?int
    {
        if (in_array($actorId, $resolvedApprovers, true)) {
            return null;
        }

        $delegator = $this->delegationResolver->delegatorFor($actorId, $schoolId, $approvableType, $resolvedApprovers);

        if ($delegator !== null) {
            return $delegator;
        }

        throw new ApproverNotAuthorizedException('You are not an approver for this step.');
    }

    /**
     * @param  array<int, int>  $resolvedApprovers
     */
    private function stepSatisfied(ApprovalRequest $request, ApprovalStep $step, array $resolvedApprovers): bool
    {
        $approvedIdentities = ApprovalActionRecord::where('request_id', $request->id)
            ->where('step_number', $step->step_number)
            ->where('action', 'approved')
            ->get()
            ->map(fn (ApprovalActionRecord $action): int => $action->on_behalf_of_id ?? $action->actor_id)
            ->unique();

        return match ($step->mode) {
            'sequential' => $approvedIdentities->count() >= 1,
            'parallel_all' => $resolvedApprovers !== [] && $approvedIdentities->intersect($resolvedApprovers)->count() >= count($resolvedApprovers),
            'parallel_any' => $approvedIdentities->count() >= $step->required_approvals,
            default => false,
        };
    }
}
