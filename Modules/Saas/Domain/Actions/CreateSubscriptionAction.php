<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\DataObjects\CreateSubscriptionData;
use Modules\Saas\Domain\Support\SchoolModuleSynchroniser;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionPlan;

/**
 * ACT-CreateSubscription (Book J SAA-01 §4/BR-SAA-01-002). Opens the
 * vendor's contract with a tenant and immediately entitles every
 * covered school to the plan's modules — a school is never left
 * waiting for a separate step before it can use what it just
 * subscribed to.
 */
final class CreateSubscriptionAction extends Action
{
    public function __construct(
        private readonly SchoolModuleSynchroniser $synchroniser,
    ) {}

    public function execute(CreateSubscriptionData $data): Subscription
    {
        $plan = SubscriptionPlan::query()->findOrFail($data->planId);

        return $this->transaction(function () use ($data, $plan): Subscription {
            $subscription = Subscription::create([
                'tenant_id' => $data->tenantId,
                'plan_id' => $plan->id,
                'covered_school_ids' => $data->coveredSchoolIds,
                'billing_currency' => $data->billingCurrency,
                'learner_count_at_billing' => $data->learnerCountAtBilling,
                'status' => $data->status,
                'trial_ends_at' => $data->trialEndsAt,
                'current_period_start' => $data->currentPeriodStart->toDateString(),
                'current_period_end' => $data->currentPeriodEnd->toDateString(),
            ]);

            $this->synchroniser->syncForPlan($data->coveredSchoolIds, $plan);

            Tenant::query()->whereKey($data->tenantId)->update(['status' => $data->status]);

            return $subscription;
        });
    }
}
