<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\Support\TransitionsSubscriptionStatus;
use Modules\Saas\Models\Subscription;

/**
 * ACT-MarkSubscriptionPastDue (Book J SAA-01 §3/§4). An unpaid invoice
 * crossed its due date — degrade to `past_due` per `EnsureSubscriptionActive`
 * (read-only, never a lockout).
 */
final class MarkSubscriptionPastDueAction extends Action
{
    use TransitionsSubscriptionStatus;

    public function execute(int $subscriptionId): Subscription
    {
        $subscription = Subscription::query()->findOrFail($subscriptionId);

        return $this->transaction(fn (): Subscription => $this->transitionStatus($subscription, 'past_due'));
    }
}
