<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\WebhookSubscription;

/**
 * ACT-CreateWebhookSubscription (Book J INT-04 §2). `signing_secret` is
 * generated server-side — the subscriber never chooses their own
 * signing key, so a leaked target URL alone can't be used to forge a
 * valid signature.
 */
final class CreateWebhookSubscriptionAction extends Action
{
    /**
     * @param  array<int, string>  $eventNames
     */
    public function execute(int $schoolId, int $clientId, array $eventNames, string $targetUrl): WebhookSubscription
    {
        return $this->transaction(fn (): WebhookSubscription => WebhookSubscription::create([
            'school_id' => $schoolId,
            'client_id' => $clientId,
            'event_names' => $eventNames,
            'target_url' => $targetUrl,
            'signing_secret' => Str::random(64),
            'is_active' => true,
            'consecutive_failures' => 0,
        ]));
    }
}
