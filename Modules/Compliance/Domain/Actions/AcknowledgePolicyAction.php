<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\AcknowledgePolicyData;
use Modules\Compliance\Models\Policy;
use Modules\Compliance\Models\PolicyAcknowledgement;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-AcknowledgePolicy (Book H3 CMP-04 §3 ⭐/BR-CMP-04-001/002
 * (AC-CMP-04-001)). Records against `policy_id`'s CURRENT `version` —
 * frozen on the row forever, so a later version never retroactively
 * changes what this acknowledgement actually agreed to.
 */
final class AcknowledgePolicyAction extends Action
{
    public function execute(AcknowledgePolicyData $data): PolicyAcknowledgement
    {
        $policy = Policy::findOrFail($data->policyId);

        return $this->transaction(fn (): PolicyAcknowledgement => PolicyAcknowledgement::create([
            'school_id' => $policy->school_id,
            'policy_id' => $policy->id,
            'policy_version' => $policy->version,
            'acknowledged_by_type' => $data->acknowledgedByType,
            'acknowledged_by_id' => $data->acknowledgedById,
            'acknowledged_at' => Carbon::now(),
            'ip_address' => $data->ipAddress,
            'method' => $data->method,
        ]));
    }
}
