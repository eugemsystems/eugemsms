<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Subscription;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\Actions\SetSubscriptionPlanActiveAction;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionPlan;

/**
 * `Saas\Subscription\Plans` (Book J SAA-01 §5, vendor console). The plan
 * catalogue and who is on each plan. Withdrawing a plan only stops new
 * sales; existing subscriptions keep it.
 */
#[Title('Plan catalogue')]
#[Layout('saas::layouts.vendor')]
final class Plans extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public function mount(): void
    {
        $this->authorizeVendor();
    }

    public function setActive(int $planId, bool $isActive): void
    {
        $operator = $this->authorizeVendor();

        $plan = app(SetSubscriptionPlanActiveAction::class)->execute($planId, $isActive);

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'plan.'.($isActive ? 'offered' : 'withdrawn'), "Plan {$plan->code} ".($isActive ? 'offered' : 'withdrawn'));

        $this->toast($isActive ? __('Plan offered.') : __('Plan withdrawn from sale.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        return view('saas::vendor.plans', [
            'plans' => SubscriptionPlan::query()->orderBy('tier')->orderBy('code')->get(),
            'subscriberCounts' => Subscription::query()->whereNotIn('status', ['cancelled'])->selectRaw('plan_id, count(*) as total')->groupBy('plan_id')->pluck('total', 'plan_id'),
        ]);
    }
}
