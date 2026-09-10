<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Intelligence\Domain\Events\WebhookAutoDisabled;
use Modules\Intelligence\Models\WebhookDelivery;
use Modules\Intelligence\Models\WebhookSubscription;
use Throwable;

/**
 * ACT-DispatchWebhook (Book J INT-04 §2/BR-INT-04-004/005
 * (AC-INT-04-002)). Every payload is signed with the subscription's
 * own `signing_secret` via HMAC-SHA256 in an `X-SERP-Signature` header
 * — the same verify-the-signature discipline this platform's own
 * inbound webhooks already carry, just outbound.
 *
 * One call is one delivery attempt; a real retry SCHEDULE (backoff,
 * queued redelivery) is deployment wiring this action doesn't own, the
 * same "meant to run on a schedule" boundary every other nightly/
 * retry-driven action in this book set already draws — but the
 * attempt bookkeeping itself (`attempt_count`, `consecutive_failures`,
 * the `integration.webhook_max_retries` → `abandoned` transition, the
 * `integration.webhook_disable_after_failures` → auto-disable) is real
 * and self-contained here, not deferred.
 */
final class DispatchWebhookAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(int $schoolId, int $subscriptionId, string $eventName, array $payload): WebhookDelivery
    {
        $subscription = WebhookSubscription::findOrFail($subscriptionId);

        $delivery = $this->transaction(fn (): WebhookDelivery => WebhookDelivery::create([
            'school_id' => $schoolId, 'subscription_id' => $subscriptionId,
            'event_name' => $eventName, 'payload' => $payload, 'attempt_count' => 0, 'status' => 'pending',
        ]));

        $body = json_encode($payload) ?: '{}';
        $signature = hash_hmac('sha256', $body, (string) $subscription->signing_secret);

        try {
            $response = Http::withHeaders(['X-SERP-Signature' => $signature])->post($subscription->target_url, $payload);
            $succeeded = $response->successful();
            $responseStatus = $response->status();
        } catch (Throwable) {
            $succeeded = false;
            $responseStatus = null;
        }

        $scope = new ScopeChain(schoolId: $schoolId);
        $maxRetries = (int) $this->settings->get('integration.webhook_max_retries', $scope);
        $disableThreshold = (int) $this->settings->get('integration.webhook_disable_after_failures', $scope);
        $attemptCount = $delivery->attempt_count + 1;

        $status = match (true) {
            $succeeded => 'delivered',
            $attemptCount >= $maxRetries => 'abandoned',
            default => 'failed',
        };

        $delivery = $this->transaction(function () use ($delivery, $attemptCount, $status, $responseStatus): WebhookDelivery {
            $delivery->update([
                'attempt_count' => $attemptCount, 'status' => $status,
                'response_status' => $responseStatus, 'last_attempted_at' => Carbon::now(),
            ]);

            return $delivery->fresh();
        });

        $this->transaction(function () use ($subscription, $succeeded, $disableThreshold): void {
            if ($succeeded) {
                $subscription->update(['consecutive_failures' => 0]);

                return;
            }

            $failures = $subscription->consecutive_failures + 1;
            $subscription->update(['consecutive_failures' => $failures]);

            if ($failures >= $disableThreshold && $subscription->is_active) {
                $subscription->update(['is_active' => false]);
                event(new WebhookAutoDisabled($subscription->fresh()));
            }
        });

        return $delivery;
    }
}
