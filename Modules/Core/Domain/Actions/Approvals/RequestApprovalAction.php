<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Approvals;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Approvals\RequestApprovalData;
use Modules\Core\Domain\Events\Approvals\ApprovalRequested;
use Modules\Core\Domain\Events\Approvals\ApprovalStepBlocked;
use Modules\Core\Domain\Support\Approvals\ApprovalChainSelector;
use Modules\Core\Domain\Support\Approvals\ApproverResolver;
use Modules\Core\Models\ApprovalRequest;

/**
 * ACT-RequestApproval (Book A CORE-07 §3/BR-CORE-07-001/003). Chain
 * selection and first-step approver resolution both happen here, at
 * creation — a step with no resolvable approver leaves the request
 * `pending` and raises `ApprovalStepBlocked` rather than refusing to
 * create the request at all (BR-CORE-07-003 is about auto-approval,
 * not about creation).
 */
final class RequestApprovalAction extends Action
{
    public function __construct(
        private readonly ApprovalChainSelector $chainSelector,
        private readonly ApproverResolver $approverResolver,
    ) {}

    public function execute(RequestApprovalData $data): ApprovalRequest
    {
        $chain = $this->chainSelector->select($data->schoolId, $data->approvable);
        $amount = $data->approvable->approvalAmount();

        return $this->transaction(function () use ($data, $chain, $amount): ApprovalRequest {
            $request = ApprovalRequest::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'chain_id' => $chain->id,
                'approvable_type' => $data->approvable->getMorphClass(),
                'approvable_id' => $data->approvable->getKey(),
                'current_step' => 1,
                'status' => 'pending',
                'title' => $data->approvable->approvalTitle(),
                'summary' => $data->approvable->approvalSummary(),
                'amount_minor' => $amount?->minor,
                'amount_currency' => $amount?->currency->value,
                'requested_by' => $data->requestedByUserId,
                'requested_at' => Carbon::now(),
                'due_at' => $data->dueInHours !== null ? Carbon::now()->addHours($data->dueInHours) : null,
            ]);

            event(new ApprovalRequested($request));

            $firstStep = $chain->steps()->where('step_number', 1)->first();

            if ($firstStep !== null && $this->approverResolver->resolve($firstStep, $data->schoolId, $data->approvable) === []) {
                event(new ApprovalStepBlocked($request));
            }

            return $request;
        });
    }
}
