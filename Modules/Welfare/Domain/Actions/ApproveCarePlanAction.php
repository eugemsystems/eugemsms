<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\Events\CarePlanApproved;
use Modules\Welfare\Models\EmergencyCarePlan;

/**
 * ACT-ApproveCarePlan (Book G BRD-06 §7 — `CarePlanApproved` event).
 */
final class ApproveCarePlanAction extends Action
{
    public function execute(int $carePlanId, int $approvedByNurseUserId): EmergencyCarePlan
    {
        $plan = EmergencyCarePlan::findOrFail($carePlanId);

        return $this->transaction(function () use ($plan, $approvedByNurseUserId): EmergencyCarePlan {
            $plan->update([
                'approved_by_nurse' => $approvedByNurseUserId,
                'approved_at' => Carbon::now(),
            ]);

            event(new CarePlanApproved($plan));

            return $plan;
        });
    }
}
