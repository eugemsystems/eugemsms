<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\Support\TransitionsSubscriptionStatus;
use Modules\Saas\Models\Subscription;

/**
 * ACT-ReactivateSubscription (Book J SAA-01 §3/§4). Payment resolved a
 * `past_due`/`grace`/`suspended` tenant — restores `active` and clears
 * the grace deadline.
 */
final class ReactivateSubscriptionAction extends Action
{
    use TransitionsSubscriptionStatus;

    public function execute(int $subscriptionId): Subscription
    {
        $subscription = Subscription::query()->findOrFail($subscriptionId);

        return $this->transaction(fn (): Subscription => $this->transitionStatus($subscription, 'active', ['grace_period_ends_at' => null]));
    }
}
