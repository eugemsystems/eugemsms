<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Approvals;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Approvals\Approvable;
use Modules\Core\Domain\DataObjects\Approvals\ResubmitApprovalData;
use Modules\Core\Domain\Events\Approvals\ApprovalStepBlocked;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Approvals\ApproverResolver;
use Modules\Core\Models\ApprovalRequest;

/**
 * ACT-ResubmitApproval (Book A CORE-07 BR-CORE-07-007). Resubmission
 * restarts at step 1 — this module has no per-chain override for that
 * default yet.
 */
final class ResubmitApprovalAction extends Action
{
    public function __construct(
        private readonly ApproverResolver $approverResolver,
    ) {}

    public function execute(ResubmitApprovalData $data): ApprovalRequest
    {
        $request = ApprovalRequest::withoutGlobalScopes()->findOrFail($data->requestId);

        if ($request->status !== 'returned') {
            throw new class("Only a returned request can be resubmitted; this one is [{$request->status}].") extends DomainException
            {
                public function errorCode(): string
                {
                    return 'NOT_RETURNED';
                }
            };
        }

        return $this->transaction(function () use ($request): ApprovalRequest {
            $request->forceFill(['status' => 'pending', 'current_step' => 1])->save();

            $step = $request->currentStep();
            /** @var Approvable $approvable */
            $approvable = $request->resolveApprovable();

            if ($step !== null && $this->approverResolver->resolve($step, $request->school_id, $approvable) === []) {
                event(new ApprovalStepBlocked($request));
            }

            return $request;
        });
    }
}
