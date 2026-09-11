<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\DataObjects\ChangeSubscriptionPlanData;
use Modules\Saas\Domain\DataObjects\IssueTenantInvoiceData;
use Modules\Saas\Domain\Support\SchoolModuleSynchroniser;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionChange;
use Modules\Saas\Models\SubscriptionPlan;

/**
 * ACT-ChangeSubscriptionPlan (Book J SAA-01 §4/BR-SAA-01-006
 * ⭐/AC-SAA-01-003). Direction is derived, never caller-supplied: a plan
 * that costs more (its monthly-equivalent price at the tenant's current
 * learner count) is an upgrade and takes effect now, with the unused
 * remainder of the current plan credited against a prorated invoice for
 * the difference; anything else is a downgrade and only takes effect at
 * `RenewSubscriptionAction`'s next rollover, so a school never loses
 * access to data created under a higher tier partway through using it.
 */
final class ChangeSubscriptionPlanAction extends Action
{
    public function __construct(
        private readonly SchoolModuleSynchroniser $synchroniser,
        private readonly IssueTenantInvoiceAction $issueInvoice,
    ) {}

    public function execute(ChangeSubscriptionPlanData $data): SubscriptionChange
    {
        $subscription = Subscription::query()->findOrFail($data->subscriptionId);
        $fromPlan = SubscriptionPlan::query()->findOrFail($subscription->plan_id);
        $toPlan = SubscriptionPlan::query()->findOrFail($data->newPlanId);

        $learnerCount = $subscription->learner_count_at_billing ?? 0;
        $isUpgrade = $toPlan->monthlyPriceMinor($learnerCount) > $fromPlan->monthlyPriceMinor($learnerCount);

        return $this->transaction(function () use ($subscription, $fromPlan, $toPlan, $data, $isUpgrade, $learnerCount): SubscriptionChange {
            $prorationCreditMinor = $this->prorationCreditMinor($subscription, $fromPlan, $learnerCount);

            $change = SubscriptionChange::create([
                'subscription_id' => $subscription->id,
                'change_type' => $isUpgrade ? 'upgrade' : 'downgrade',
                'from_plan_id' => $fromPlan->id,
                'to_plan_id' => $toPlan->id,
                'proration_credit_minor' => $isUpgrade ? $prorationCreditMinor : null,
                'effective_from' => $isUpgrade ? Carbon::today()->toDateString() : $subscription->current_period_end->toDateString(),
                'reason' => $data->reason,
                'performed_by' => $data->performedBy,
                'occurred_at' => Carbon::now(),
            ]);

            if ($isUpgrade) {
                $subscription->update(['plan_id' => $toPlan->id]);
                $this->synchroniser->syncForPlan($subscription->covered_school_ids, $toPlan);

                $chargeMinor = max(0, $toPlan->monthlyPriceMinor($learnerCount) - $prorationCreditMinor);

                $this->issueInvoice->execute(new IssueTenantInvoiceData(
                    subscriptionId: $subscription->id,
                    periodMonth: Carbon::today()->format('Y-m'),
                    lineItems: [
                        ['description' => "Upgrade from {$fromPlan->code} to {$toPlan->code}, prorated", 'amount_minor' => $chargeMinor],
                    ],
                    dueInDays: 7,
                ));
            }

            return $change;
        });
    }

    private function prorationCreditMinor(Subscription $subscription, SubscriptionPlan $fromPlan, int $learnerCount): int
    {
        $daysInPeriod = $subscription->current_period_start->diffInDays($subscription->current_period_end, absolute: true) + 1;
        $remainingDays = max(0, (int) Carbon::today()->diffInDays($subscription->current_period_end, absolute: true));

        $oldMonthly = $fromPlan->monthlyPriceMinor($learnerCount);

        return (int) round($oldMonthly * $remainingDays / max(1, $daysInPeriod));
    }
}
