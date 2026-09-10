<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Approvals;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Approvals\CreateApprovalChainData;
use Modules\Core\Models\ApprovalChain;

final class CreateApprovalChainAction extends Action
{
    public function execute(CreateApprovalChainData $data): ApprovalChain
    {
        return $this->transaction(function () use ($data): ApprovalChain {
            $chain = ApprovalChain::create([
                'school_id' => $data->schoolId,
                'approvable_type' => $data->approvableType,
                'name' => $data->name,
                'description' => $data->description,
                'condition_rules' => $data->conditionRules,
                'is_default' => $data->isDefault,
                'priority' => $data->priority,
                'is_active' => true,
                'created_by' => $data->createdByUserId,
                'updated_by' => $data->createdByUserId,
            ]);

            foreach ($data->steps as $step) {
                $chain->steps()->create([
                    'step_number' => $step->stepNumber,
                    'name' => $step->name,
                    'approver_type' => $step->approverType,
                    'approver_role_id' => $step->approverRoleId,
                    'approver_user_id' => $step->approverUserId,
                    'dynamic_resolver' => $step->dynamicResolver,
                    'mode' => $step->mode,
                    'required_approvals' => $step->requiredApprovals,
                    'condition_rules' => $step->conditionRules,
                    'escalate_after_hours' => $step->escalateAfterHours,
                    'escalate_to_role_id' => $step->escalateToRoleId,
                    'can_reject' => $step->canReject,
                    'can_return' => $step->canReturn,
                    'requires_comment' => $step->requiresComment,
                ]);
            }

            return $chain;
        });
    }
}
