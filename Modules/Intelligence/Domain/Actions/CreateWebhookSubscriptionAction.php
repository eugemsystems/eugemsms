<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\Support\WebhookTargetUrl;
use Modules\Intelligence\Models\ApiClient;
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
        WebhookTargetUrl::assertSafe($targetUrl);

        $eventNames = array_values(array_unique($eventNames));

        if ($eventNames === [] || count($eventNames) > 50) {
            throw new InvalidArgumentException('Subscribe to between 1 and 50 events.');
        }

        foreach ($eventNames as $eventName) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9_.]{0,79}$/', $eventName) !== 1) {
                throw new InvalidArgumentException("[{$eventName}] is not a valid event name.");
            }
        }

        // The client must be one of this school's own live integrations — never a
        // device credential, a revoked key, or another school's client.
        ApiClient::where('school_id', $schoolId)->where('client_type', 'integration')->where('is_active', true)->findOrFail($clientId);

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
