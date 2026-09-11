<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\Support\TransitionsSubscriptionStatus;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionChange;

/**
 * ACT-CancelSubscription (Book J SAA-01 §3/§4/BR-SAA-01-008
 * ⭐/AC-SAA-01-005). Read and `CORE-13` export access continue
 * unbroken through the retention window — cancellation never means
 * data loss or invisibility (§3).
 */
final class CancelSubscriptionAction extends Action
{
    use TransitionsSubscriptionStatus;

    public function execute(int $subscriptionId, ?string $reason = null, ?int $performedBy = null): Subscription
    {
        $subscription = Subscription::query()->findOrFail($subscriptionId);

        return $this->transaction(function () use ($subscription, $reason, $performedBy): Subscription {
            $result = $this->transitionStatus($subscription, 'cancelled', [
                'cancelled_at' => Carbon::now(),
                'cancellation_reason' => $reason,
            ]);

            SubscriptionChange::create([
                'subscription_id' => $subscription->id,
                'change_type' => 'cancelled',
                'from_plan_id' => $subscription->plan_id,
                'to_plan_id' => null,
                'effective_from' => Carbon::today()->toDateString(),
                'reason' => $reason,
                'performed_by' => $performedBy,
                'occurred_at' => Carbon::now(),
            ]);

            return $result;
        });
    }
}
