<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\SubscriptionChange;

/**
 * @extends Factory<SubscriptionChange>
 */
class SubscriptionChangeFactory extends Factory
{
    protected $model = SubscriptionChange::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'change_type' => 'upgrade',
            'from_plan_id' => null,
            'to_plan_id' => null,
            'proration_credit_minor' => null,
            'effective_from' => Carbon::today()->toDateString(),
            'reason' => null,
            'performed_by' => null,
            'occurred_at' => Carbon::now(),
        ];
    }
}
