<?php

use Illuminate\Support\Facades\Http;
use Modules\Core\Domain\Registry\ScheduledTaskHandlerRegistry;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\CreateWebhookSubscriptionAction;
use Modules\Intelligence\Domain\Actions\DispatchWebhookAction;
use Modules\Intelligence\Domain\Actions\IssueApiClientAction;
use Modules\Intelligence\Domain\Actions\RetryFailedWebhookDeliveriesAction;
use Modules\Intelligence\Models\WebhookDelivery;
use Modules\Intelligence\Models\WebhookSubscription;

/**
 * @return array{school: School, subscription: WebhookSubscription}
 */
function int04bHook(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $issued = app(IssueApiClientAction::class)->execute($school->id, 'BI Tool', 'integration', ['usage:read']);
    $subscription = app(CreateWebhookSubscriptionAction::class)->execute($school->id, $issued['client']->id, ['InvoiceIssued'], 'https://example.test/hook');

    return compact('school', 'subscription');
}

it('registers the webhook retry as a per-school scheduled task run by serp:run-task', function (): void {
    expect(ScheduledTaskHandlerRegistry::all())->toHaveKey('intelligence.retry_webhook_deliveries');
});

it('retries a failed delivery once its backoff has elapsed and records it as delivered', function (): void {
    Http::fakeSequence()->push('error', 500)->push('ok', 200);
    $f = int04bHook();

    $delivery = app(DispatchWebhookAction::class)->execute($f['school']->id, $f['subscription']->id, 'InvoiceIssued', ['id' => 1]);
    expect($delivery->status)->toBe('failed')->and($delivery->attempt_count)->toBe(1);

    // Still inside the backoff window: nothing is retried.
    expect(app(RetryFailedWebhookDeliveriesAction::class)->execute($f['school']->id))->toBe([]);

    $this->travel(5)->minutes();
    $retried = app(RetryFailedWebhookDeliveriesAction::class)->execute($f['school']->id);

    expect($retried)->toHaveCount(1)
        ->and($retried[0]->id)->toBe($delivery->id)
        ->and($retried[0]->status)->toBe('delivered')
        ->and($retried[0]->attempt_count)->toBe(2)
        ->and(WebhookDelivery::count())->toBe(1);
});

it('abandons a delivery at the max-retries setting and stops retrying it', function (): void {
    Http::fake(fn () => Http::response('error', 500));
    $f = int04bHook();

    app(DispatchWebhookAction::class)->execute($f['school']->id, $f['subscription']->id, 'InvoiceIssued', ['id' => 1]);

    for ($i = 0; $i < 10; $i++) {
        $this->travel(7)->hours();
        app(RetryFailedWebhookDeliveriesAction::class)->execute($f['school']->id);
    }

    $delivery = WebhookDelivery::first();
    expect($delivery->status)->toBe('abandoned')->and($delivery->attempt_count)->toBe(8)
        ->and(app(RetryFailedWebhookDeliveriesAction::class)->execute($f['school']->id))->toBe([]);
});

it('does not retry deliveries of a disabled subscription', function (): void {
    Http::fake(fn () => Http::response('error', 500));
    $f = int04bHook();

    app(DispatchWebhookAction::class)->execute($f['school']->id, $f['subscription']->id, 'InvoiceIssued', ['id' => 1]);
    $f['subscription']->update(['is_active' => false]);
    $this->travel(7)->hours();

    expect(app(RetryFailedWebhookDeliveriesAction::class)->execute($f['school']->id))->toBe([]);
});
