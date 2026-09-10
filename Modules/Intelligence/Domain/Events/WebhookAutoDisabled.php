<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Events;

use Modules\Intelligence\Models\WebhookSubscription;

/**
 * ⚠ Book J INT-04 §5/BR-INT-04-005 (AC-INT-04-002).
 */
final class WebhookAutoDisabled
{
    public function __construct(
        public readonly WebhookSubscription $subscription,
    ) {}
}
