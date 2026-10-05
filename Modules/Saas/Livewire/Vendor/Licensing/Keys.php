<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Licensing;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Saas\Domain\Actions\IssueLicenceKeyAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\DataObjects\IssueLicenceKeyData;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\LicenceKey;
use Modules\Saas\Models\Subscription;

/**
 * `Saas\Licensing\Keys` (Book J SAA-01 §5, vendor console). On-premise
 * licence keys per subscription. The tenant is read from the subscription,
 * never chosen separately, so a key can only ever be bound to the tenant
 * that owns the subscription.
 */
#[Title('Licence keys')]
#[Layout('saas::layouts.vendor')]
final class Keys extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public ?int $subscriptionId = null;

    public int $offlineGraceDays = 14;

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function issue(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate(['subscriptionId' => ['required', 'integer'], 'offlineGraceDays' => ['required', 'integer', 'min:0', 'max:90']]);

        $subscription = Subscription::query()->findOrFail($this->subscriptionId);

        try {
            $key = app(IssueLicenceKeyAction::class)->execute(new IssueLicenceKeyData($subscription->tenant_id, $subscription->id, $this->offlineGraceDays));
        } catch (InvalidArgumentException $exception) {
            $this->addError('subscriptionId', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'licence.issued', 'Licence key issued', $subscription->tenant_id, ['licence_key_id' => $key->id]);

        $this->toast(__('Licence key issued.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        return view('saas::vendor.keys', [
            'keys' => LicenceKey::query()->with('tenant')->orderByDesc('id')->limit(100)->get(),
            'subscriptions' => Subscription::query()->with('tenant')->whereNotIn('status', ['cancelled'])->orderByDesc('id')->limit(200)->get(),
        ]);
    }
}
