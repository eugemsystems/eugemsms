<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\WebhookDelivery;
use Modules\Intelligence\Models\WebhookSubscription;

/**
 * ACT-TriggerWebhooksForEvent (Book J INT-04 §2). Finds every active
 * subscription for this school that named this event, and dispatches
 * one delivery per subscription via the real `DispatchWebhookAction`.
 *
 * Wiring this to fire automatically off the application's own domain
 * events (`InvoiceIssued`, `ReceiptVoided`, etc.) is deployment-level
 * listener registration, not this action's own concern — the same
 * boundary every other "the real mechanism exists, hooking it to every
 * call site is separate wiring work" note in this book set draws.
 */
final class TriggerWebhooksForEventAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly DispatchWebhookAction $dispatchWebhook,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, WebhookDelivery>
     */
    public function execute(int $schoolId, string $eventName, array $payload): array
    {
        $subscriptions = WebhookSubscription::where('school_id', $schoolId)->where('is_active', true)->get()
            ->filter(fn (WebhookSubscription $s): bool => $s->subscribesTo($eventName));

        return $subscriptions
            ->map(fn (WebhookSubscription $s): WebhookDelivery => $this->dispatchWebhook->execute($schoolId, $s->id, $eventName, $payload))
            ->values()
            ->all();
    }
}
