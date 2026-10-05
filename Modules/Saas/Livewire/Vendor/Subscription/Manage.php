<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Subscription;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\CancelSubscriptionAction;
use Modules\Saas\Domain\Actions\CreateSubscriptionAction;
use Modules\Saas\Domain\Actions\MarkSubscriptionPastDueAction;
use Modules\Saas\Domain\Actions\ReactivateSubscriptionAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Domain\Actions\RenewSubscriptionAction;
use Modules\Saas\Domain\Actions\SuspendSubscriptionAction;
use Modules\Saas\Domain\DataObjects\CreateSubscriptionData;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionPlan;

/**
 * `Saas\Subscription\Manage` (Book J SAA-01 §5, vendor console).
 * Subscription lifecycle across tenants: open one, then suspend,
 * reactivate, mark past due, renew or cancel. The lifecycle Actions own
 * the state machine and refuse an illegal move; this screen offers only
 * the moves a status allows and shows the refusal if a stale page tries
 * another. Every move is written to the vendor audit trail (BR-SAA-02-007).
 */
#[Title('Subscriptions')]
#[Layout('saas::layouts.vendor')]
final class Manage extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    /** @var array<string, array<int, string>> */
    private const array MOVES = [
        'trial' => ['past_due', 'suspend', 'cancel', 'renew'],
        'active' => ['past_due', 'suspend', 'cancel', 'renew'],
        'past_due' => ['reactivate', 'suspend', 'cancel'],
        'grace' => ['reactivate', 'suspend', 'cancel'],
        'suspended' => ['reactivate', 'cancel'],
        'cancelled' => [],
    ];

    public string $statusFilter = '';

    public string $cancelReason = '';

    public ?int $tenantId = null;

    public ?int $planId = null;

    /** @var array<int, int> */
    public array $schoolIds = [];

    public string $currency = 'USD';

    public string $periodStart = '';

    public string $periodEnd = '';

    public string $status = 'trial';

    public ?int $learnerCount = null;

    public function mount(): void
    {
        $this->authorizeVendor();

        $this->periodStart = now()->toDateString();
        $this->periodEnd = now()->addMonth()->toDateString();
    }

    public function updatedTenantId(): void
    {
        $this->schoolIds = [];
    }

    public function create(): void
    {
        $operator = $this->authorizeVendor();
        $this->resetErrorBag();

        $this->validate([
            'tenantId' => ['required', 'integer'],
            'planId' => ['required', 'integer'],
            'schoolIds' => ['required', 'array', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after:periodStart'],
            'status' => ['required', 'in:trial,active'],
            'learnerCount' => ['nullable', 'integer', 'min:0'],
        ]);

        $tenant = Tenant::query()->findOrFail($this->tenantId);

        try {
            $subscription = app(CreateSubscriptionAction::class)->execute(new CreateSubscriptionData(
                tenantId: $tenant->id,
                planId: (int) $this->planId,
                coveredSchoolIds: array_map('intval', $this->schoolIds),
                billingCurrency: strtoupper($this->currency),
                currentPeriodStart: Carbon::parse($this->periodStart),
                currentPeriodEnd: Carbon::parse($this->periodEnd),
                status: $this->status,
                trialEndsAt: $this->status === 'trial' ? Carbon::parse($this->periodEnd) : null,
                learnerCountAtBilling: $this->learnerCount,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('tenantId', $exception->getMessage());

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'subscription.opened', "Opened a {$this->status} subscription for {$tenant->name}", $tenant->id, ['subscription_id' => $subscription->id, 'plan_id' => $subscription->plan_id]);

        $this->reset('tenantId', 'planId', 'schoolIds', 'learnerCount');
        $this->toast(__('Subscription opened and modules entitled.'));
    }

    public function move(int $subscriptionId, string $move): void
    {
        $operator = $this->authorizeVendor();

        $subscription = Subscription::query()->findOrFail($subscriptionId);
        abort_unless(in_array($move, self::MOVES[$subscription->status] ?? [], true), 422);

        if ($move === 'cancel' && trim($this->cancelReason) === '') {
            $this->toast(__('Give a reason before cancelling.'), 'danger');

            return;
        }

        try {
            match ($move) {
                'past_due' => app(MarkSubscriptionPastDueAction::class)->execute($subscription->id),
                'suspend' => app(SuspendSubscriptionAction::class)->execute($subscription->id),
                'reactivate' => app(ReactivateSubscriptionAction::class)->execute($subscription->id),
                'renew' => app(RenewSubscriptionAction::class)->execute($subscription->id),
                'cancel' => app(CancelSubscriptionAction::class)->execute($subscription->id, trim($this->cancelReason), $operator->id),
            };
        } catch (InvalidStateTransitionException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        app(RecordVendorConsoleActionAction::class)->execute($operator, "subscription.{$move}", "Subscription {$subscription->id}: {$move}", $subscription->tenant_id, ['subscription_id' => $subscription->id, 'from' => $subscription->status, 'reason' => $move === 'cancel' ? trim($this->cancelReason) : null]);

        $this->cancelReason = '';
        $this->toast(__('Done.'));
    }

    public function render(): View
    {
        $this->authorizeVendor();

        $subscriptions = Subscription::query()->with(['tenant', 'plan'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')->limit(100)->get();

        return view('saas::vendor.subscriptions', [
            'subscriptions' => $subscriptions,
            'moves' => self::MOVES,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('tier')->get(['id', 'name', 'code']),
            'schools' => $this->tenantId === null ? collect() : School::query()->where('tenant_id', $this->tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
