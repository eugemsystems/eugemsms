<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionPlan;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => SubscriptionPlan::factory(),
            'covered_school_ids' => [],
            'billing_currency' => 'USD',
            'learner_count_at_billing' => 100,
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => Carbon::today()->startOfMonth()->toDateString(),
            'current_period_end' => Carbon::today()->endOfMonth()->toDateString(),
            'grace_period_ends_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'auto_renew' => true,
        ];
    }

    public function status(string $status): self
    {
        return $this->state(['status' => $status]);
    }

    public function trial(): self
    {
        return $this->state([
            'status' => 'trial',
            'trial_ends_at' => Carbon::today()->addDays(30),
        ]);
    }
}
