<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\WebhookDelivery;

/**
 * ACT-RetryFailedWebhookDeliveries (Book J INT-04 BR-INT-04-005). Run by the
 * `intelligence.retry_webhook_deliveries` scheduled task. Re-attempts each `failed` delivery
 * of an active subscription once its backoff has elapsed (2^attempts minutes, capped at 6 hours).
 * `DispatchWebhookAction::attempt()` keeps the attempt count, the `abandoned` transition at
 * `integration.webhook_max_retries` and the auto-disable threshold. Deliveries of a
 * disabled subscription are left alone.
 */
final class RetryFailedWebhookDeliveriesAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly DispatchWebhookAction $dispatchWebhook,
    ) {}

    /**
     * @return array<int, WebhookDelivery>
     */
    public function execute(int $schoolId): array
    {
        $now = Carbon::now();
        $retried = [];

        $failed = WebhookDelivery::where('school_id', $schoolId)->where('status', 'failed')->orderBy('id')->get();

        foreach ($failed as $delivery) {
            $backoffMinutes = min(360, 2 ** max(1, $delivery->attempt_count));

            if ($delivery->last_attempted_at !== null && $delivery->last_attempted_at->copy()->addMinutes($backoffMinutes)->gt($now)) {
                continue;
            }

            if (! $delivery->subscription()->where('is_active', true)->exists()) {
                continue;
            }

            $retried[] = $this->dispatchWebhook->attempt($delivery);
        }

        return $retried;
    }
}
