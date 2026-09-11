<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Events;

use Modules\Saas\Models\Subscription;

/**
 * Book J SAA-01 §4 — fired on every status transition (trial/active/
 * past_due/grace/suspended/cancelled) so a listener can notify the
 * tenant's admin; delivery itself is not wired in this pass — see
 * `UsageSoftWarningCrossed`.
 */
final class SubscriptionStatusChanged
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $previousStatus,
    ) {}
}
