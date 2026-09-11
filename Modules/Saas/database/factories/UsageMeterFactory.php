<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\UsageMeter;

/**
 * @extends Factory<UsageMeter>
 */
class UsageMeterFactory extends Factory
{
    protected $model = UsageMeter::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'period_month' => Carbon::today()->format('Y-m'),
            'metric' => UsageMeter::METRIC_ACTIVE_LEARNERS,
            'usage_value' => 100,
            'limit_value' => 500,
            'soft_warning_sent' => false,
            'hard_limit_reached' => false,
        ];
    }
}
