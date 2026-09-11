<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Support;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Events\SubscriptionStatusChanged;
use Modules\Saas\Models\Subscription;

/**
 * Book J SAA-01 §3/§4 — every subscription lifecycle Action ends the
 * same way: `subscriptions.status` changes, `Tenant::status` (Book A
 * Part 1.10) is kept an exact mirror of it in the same transaction so
 * `EnsureSubscriptionActive` never joins to `subscriptions` on every
 * request, and a listener downstream can act on the transition.
 *
 * @mixin Action
 */
trait TransitionsSubscriptionStatus
{
    /**
     * @param  array<string, mixed>  $extra
     */
    private function transitionStatus(Subscription $subscription, string $newStatus, array $extra = []): Subscription
    {
        $previousStatus = $subscription->status;

        $subscription->update(['status' => $newStatus, ...$extra]);
        Tenant::query()->whereKey($subscription->tenant_id)->update(['status' => $newStatus]);

        event(new SubscriptionStatusChanged($subscription->fresh(), $previousStatus));

        return $subscription->fresh();
    }
}
