<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\Support\TransitionsSubscriptionStatus;
use Modules\Saas\Models\Subscription;

/**
 * ACT-SuspendSubscription (Book J SAA-01 §3/§4). The grace period
 * lapsed with no payment — `EnsureSubscriptionActive` blocks writes
 * outright now except `BRD-08` safeguarding routes, which are never
 * gated (§3 ⭐).
 */
final class SuspendSubscriptionAction extends Action
{
    use TransitionsSubscriptionStatus;

    public function execute(int $subscriptionId): Subscription
    {
        $subscription = Subscription::query()->findOrFail($subscriptionId);

        return $this->transaction(fn (): Subscription => $this->transitionStatus($subscription, 'suspended'));
    }
}
