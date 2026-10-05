<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Models\SubscriptionPlan;

/**
 * ACT-SetSubscriptionPlanActive (Book J SAA-01 §5, plan catalogue).
 * Withdrawing a plan only stops it being offered to new subscriptions and
 * to plan changes; every existing subscription keeps it, so nobody loses
 * entitlements by a catalogue edit.
 */
final class SetSubscriptionPlanActiveAction extends Action
{
    public function execute(int $planId, bool $isActive): SubscriptionPlan
    {
        $plan = SubscriptionPlan::query()->findOrFail($planId);

        return $this->transaction(function () use ($plan, $isActive): SubscriptionPlan {
            $plan->update(['is_active' => $isActive]);

            return $plan->fresh();
        });
    }
}
