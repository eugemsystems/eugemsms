<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\DataObjects\IssueTenantInvoiceData;
use Modules\Saas\Domain\Support\SchoolModuleSynchroniser;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionChange;
use Modules\Saas\Models\SubscriptionPlan;

/**
 * ACT-RenewSubscription (Book J SAA-01 §4/BR-SAA-01-006). The period
 * rollover: applies any downgrade scheduled by `ChangeSubscriptionPlanAction`
 * whose `effective_from` has now arrived, advances the billing period,
 * and issues the next period's invoice. Meant to run on a schedule
 * (once per subscription per period end) — the same "meant to run
 * nightly, wiring deferred" boundary this book's other periodic
 * actions already draw (see `Modules\Intelligence\Domain\Actions\DispatchWebhookAction`).
 */
final class RenewSubscriptionAction extends Action
{
    public function __construct(
        private readonly SchoolModuleSynchroniser $synchroniser,
        private readonly IssueTenantInvoiceAction $issueInvoice,
    ) {}

    public function execute(int $subscriptionId): Subscription
    {
        $subscription = Subscription::query()->findOrFail($subscriptionId);

        return $this->transaction(function () use ($subscription): Subscription {
            $pendingDowngrade = SubscriptionChange::query()
                ->where('subscription_id', $subscription->id)
                ->where('change_type', 'downgrade')
                ->where('to_plan_id', '!=', $subscription->plan_id)
                ->whereDate('effective_from', '<=', Carbon::today()->toDateString())
                ->orderByDesc('effective_from')
                ->first();

            if ($pendingDowngrade !== null) {
                $newPlan = SubscriptionPlan::query()->findOrFail($pendingDowngrade->to_plan_id);
                $subscription->update(['plan_id' => $newPlan->id]);
                $this->synchroniser->syncForPlan($subscription->covered_school_ids, $newPlan);
            }

            $newPeriodStart = $subscription->current_period_end->copy()->addDay();
            $newPeriodEnd = $newPeriodStart->copy()->addMonthNoOverflow()->subDay();

            $subscription->update([
                'current_period_start' => $newPeriodStart->toDateString(),
                'current_period_end' => $newPeriodEnd->toDateString(),
            ]);

            $this->issueInvoice->execute(new IssueTenantInvoiceData(
                subscriptionId: $subscription->id,
                periodMonth: $newPeriodStart->format('Y-m'),
            ));

            return $subscription->fresh();
        });
    }
}
