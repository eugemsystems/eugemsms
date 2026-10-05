<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Tenant\Subscription;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Saas\Domain\Actions\ChangeSubscriptionPlanAction;
use Modules\Saas\Domain\Actions\GetMySubscriptionAction;
use Modules\Saas\Domain\DataObjects\ChangeSubscriptionPlanData;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionChange;
use Modules\Saas\Models\SubscriptionPlan;
use Modules\Saas\Models\UsageMeter;

/**
 * `Saas\Subscription\MySubscription` (Book J SAA-01 §5, tenant owner).
 * Plan, usage, invoices and plan changes for the signed-in user's OWN
 * tenant only (AC-SAA-01-006): the tenant is the user's, checked against
 * the school in the URL, and the subscription is always re-read through
 * `GetMySubscriptionAction` — no subscription or invoice id ever comes from
 * the request. An upgrade is immediate and prorated; a downgrade waits
 * for the next renewal (AC-SAA-01-003) — the direction is derived by the
 * Action, and the screen only previews the same comparison.
 */
#[Title('My subscription')]
#[Layout('layouts.app')]
final class MySubscription extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('subscription.view');

        abort_unless($this->tenantId() !== null, 403);
    }

    public function changePlan(int $planId): void
    {
        $this->authorizePermission('subscription.manage');

        $subscription = $this->subscription();
        abort_if($subscription === null, 404);

        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($planId);

        try {
            $change = app(ChangeSubscriptionPlanAction::class)->execute(new ChangeSubscriptionPlanData(
                subscriptionId: $subscription->id, newPlanId: $plan->id, performedBy: (int) auth()->id(),
            ));
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast($change->change_type === 'upgrade'
            ? __('Upgraded — effective now, with a prorated invoice.')
            : __('Downgrade scheduled for your next renewal.'));
    }

    /**
     * The signed-in user's own tenant, and only if it is the tenant of the
     * school in the URL.
     */
    private function tenantId(): ?int
    {
        $userTenantId = auth()->user()?->tenant_id;

        return $userTenantId !== null && (int) $userTenantId === (int) $this->school->tenant_id ? (int) $userTenantId : null;
    }

    private function subscription(): ?Subscription
    {
        $tenantId = $this->tenantId();

        return $tenantId === null ? null : app(GetMySubscriptionAction::class)->execute($tenantId);
    }

    public function render(): View
    {
        $subscription = $this->subscription();
        $learners = $subscription === null ? 0 : ($subscription->learner_count_at_billing ?? 0);
        $current = $subscription?->plan;

        return view('saas::tenant.my-subscription', [
            'subscription' => $subscription,
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('tier')->get()->map(fn (SubscriptionPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'monthly' => $plan->monthlyPriceMinor($learners),
                'currency' => $plan->currency,
                'isCurrent' => $current !== null && $plan->id === $current->id,
                'direction' => $current === null ? null : ($plan->monthlyPriceMinor($learners) > $current->monthlyPriceMinor($learners) ? 'upgrade' : 'downgrade'),
            ]),
            'usage' => $subscription === null ? collect() : UsageMeter::query()->where('subscription_id', $subscription->id)->where('period_month', now()->format('Y-m'))->get(),
            'pendingChange' => $subscription === null ? null : SubscriptionChange::query()->where('subscription_id', $subscription->id)
                ->where('change_type', 'downgrade')->where('effective_from', '>', now()->toDateString())->latest('id')->first(),
            'invoices' => $subscription === null ? collect() : $subscription->invoices->sortByDesc('id')->take(24),
        ]);
    }
}
