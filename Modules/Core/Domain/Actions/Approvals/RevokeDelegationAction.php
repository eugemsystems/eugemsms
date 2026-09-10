<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Approvals;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Approvals\RevokeDelegationData;
use Modules\Core\Models\ApprovalDelegation;

final class RevokeDelegationAction extends Action
{
    public function execute(RevokeDelegationData $data): ApprovalDelegation
    {
        $delegation = ApprovalDelegation::withoutGlobalScopes()->findOrFail($data->delegationId);

        return $this->transaction(function () use ($delegation): ApprovalDelegation {
            $delegation->forceFill(['is_active' => false])->save();

            return $delegation;
        });
    }
}
