<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Integrations\Webhooks;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\CreateWebhookSubscriptionAction;
use Modules\Intelligence\Domain\Actions\SetWebhookSubscriptionActiveAction;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\WebhookSubscription;

/**
 * `Intelligence\Integrations\Webhooks\Index` (Book J INT-04 §5,
 * `integration.webhook.manage`). Outbound subscriptions. The signing
 * secret is generated server-side and shown once, at creation
 * (BR-INT-04-004); the target must be a public https URL — enforced by
 * the Action, not the form. An auto-disabled subscription (BR-INT-04-005)
 * is re-enabled here, which resets its failure run.
 */
#[Title('Webhook subscriptions')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $clientId = null;

    public string $events = '';

    public string $targetUrl = '';

    public ?string $revealedSecret = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('integration.webhook.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('integration.webhook.manage');
        $this->resetErrorBag();

        $this->validate([
            'clientId' => ['required', 'integer'],
            'events' => ['required', 'string', 'max:2000'],
            'targetUrl' => ['required', 'string', 'max:500'],
        ]);

        $eventNames = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $this->events) ?: [])));

        try {
            $subscription = app(CreateWebhookSubscriptionAction::class)->execute($this->school->id, (int) $this->clientId, $eventNames, $this->targetUrl);
        } catch (InvalidArgumentException $exception) {
            $this->addError('targetUrl', $exception->getMessage());

            return;
        }

        $this->revealedSecret = (string) $subscription->signing_secret;
        $this->reset('events', 'targetUrl');
    }

    public function setActive(int $subscriptionId, bool $isActive): void
    {
        $this->authorizePermission('integration.webhook.manage');

        $subscription = WebhookSubscription::where('school_id', $this->school->id)->findOrFail($subscriptionId);

        try {
            app(SetWebhookSubscriptionActiveAction::class)->execute($subscription->id, $isActive);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast($isActive ? __('Subscription enabled.') : __('Subscription disabled.'));
    }

    public function dismissSecret(): void
    {
        $this->revealedSecret = null;
    }

    public function render(): View
    {
        $subscriptions = WebhookSubscription::where('school_id', $this->school->id)->orderByDesc('id')->limit(100)->get();

        return view('intelligence::integrations.webhooks', [
            'subscriptions' => $subscriptions,
            'clients' => ApiClient::where('school_id', $this->school->id)->where('client_type', 'integration')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'clientNames' => ApiClient::where('school_id', $this->school->id)->whereIn('id', $subscriptions->pluck('client_id'))->pluck('name', 'id'),
        ]);
    }
}
