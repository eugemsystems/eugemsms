<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\School;
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

        if (! $plan->is_active) {
            throw new InvalidArgumentException('That plan is no longer offered.');
        }

        // Entitlements are written to every covered school, so each must belong to
        // this tenant — never another tenant's, whatever ids the caller passes.
        $coveredSchoolIds = array_values(array_unique($data->coveredSchoolIds));

        if ($coveredSchoolIds === [] || School::query()->where('tenant_id', $data->tenantId)->whereIn('id', $coveredSchoolIds)->count() !== count($coveredSchoolIds)) {
            throw new InvalidArgumentException('Every covered school must belong to the tenant.');
        }

        if (Subscription::query()->where('tenant_id', $data->tenantId)->whereNotIn('status', ['cancelled'])->exists()) {
            throw new InvalidArgumentException('This tenant already has a live subscription.');
        }

        return $this->transaction(function () use ($data, $plan, $coveredSchoolIds): Subscription {
            $subscription = Subscription::create([
                'tenant_id' => $data->tenantId,
                'plan_id' => $plan->id,
                'covered_school_ids' => $coveredSchoolIds,
                'billing_currency' => $data->billingCurrency,
                'learner_count_at_billing' => $data->learnerCountAtBilling,
                'status' => $data->status,
                'trial_ends_at' => $data->trialEndsAt,
                'current_period_start' => $data->currentPeriodStart->toDateString(),
                'current_period_end' => $data->currentPeriodEnd->toDateString(),
            ]);

            $this->synchroniser->syncForPlan($coveredSchoolIds, $plan);

            Tenant::query()->whereKey($data->tenantId)->update(['status' => $data->status]);

            return $subscription;
        });
    }
}
