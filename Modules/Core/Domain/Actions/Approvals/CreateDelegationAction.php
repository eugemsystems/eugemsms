<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Approvals;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Approvals\CreateDelegationData;
use Modules\Core\Domain\Events\Approvals\DelegationCreated;
use Modules\Core\Models\ApprovalDelegation;

/**
 * ACT-CreateDelegation (Book A CORE-07 BR-CORE-07-009).
 */
final class CreateDelegationAction extends Action
{
    public function execute(CreateDelegationData $data): ApprovalDelegation
    {
        return $this->transaction(function () use ($data): ApprovalDelegation {
            $delegation = ApprovalDelegation::create([
                'school_id' => $data->schoolId,
                'delegator_id' => $data->delegatorId,
                'delegate_id' => $data->delegateId,
                'approvable_type' => $data->approvableType,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'reason' => $data->reason,
                'is_active' => true,
                'created_by' => $data->createdByUserId,
            ]);

            event(new DelegationCreated($delegation));

            return $delegation;
        });
    }
}
