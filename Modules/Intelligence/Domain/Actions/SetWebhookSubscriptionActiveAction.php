<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\WebhookSubscription;

/**
 * ACT-SetWebhookSubscriptionActive (Book J INT-04 §5). Re-enabling resets
 * the failure run, so an auto-disabled subscription gets a fresh start;
 * it is refused when its owning client has since been revoked.
 */
final class SetWebhookSubscriptionActiveAction extends Action
{
    public function execute(int $subscriptionId, bool $isActive): WebhookSubscription
    {
        $subscription = WebhookSubscription::findOrFail($subscriptionId);

        if ($isActive && ! ApiClient::where('is_active', true)->whereKey($subscription->client_id)->exists()) {
            throw new InvalidArgumentException('This subscription’s API client has been revoked.');
        }

        return $this->transaction(function () use ($subscription, $isActive): WebhookSubscription {
            $subscription->update(['is_active' => $isActive, 'consecutive_failures' => $isActive ? 0 : $subscription->consecutive_failures]);

            return $subscription->fresh();
        });
    }
}
