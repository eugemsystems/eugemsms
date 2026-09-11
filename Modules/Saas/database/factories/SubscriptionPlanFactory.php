<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Saas\Models\SubscriptionPlan;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('PLAN???')),
            'name' => fake()->words(2, true),
            'tier' => 'foundation',
            'price_per_learner_minor' => 150,
            'flat_monthly_minor' => null,
            'currency' => 'USD',
            'included_modules' => ['CORE', 'PPL', 'ACA', 'FIN'],
            'learner_band_min' => 0,
            'learner_band_max' => 500,
            'seat_limit_admin' => 10,
            'storage_quota_gb' => 20,
            'message_quota_monthly' => 5000,
            'is_active' => true,
        ];
    }

    public function tier(string $tier): self
    {
        return $this->state(['tier' => $tier]);
    }

    public function flatFee(int $flatMonthlyMinor): self
    {
        return $this->state([
            'flat_monthly_minor' => $flatMonthlyMinor,
            'price_per_learner_minor' => null,
        ]);
    }
}
