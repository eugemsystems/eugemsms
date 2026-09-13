<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\PaymentPlan;
use Modules\Finance\Models\PaymentPlanInstalment;

/**
 * @extends Factory<PaymentPlanInstalment>
 */
class PaymentPlanInstalmentFactory extends Factory
{
    protected $model = PaymentPlanInstalment::class;

    public function definition(): array
    {
        return [
            'plan_id' => PaymentPlan::factory(),
            'instalment_number' => 1,
            'due_date' => now()->addMonth()->toDateString(),
            'amount_minor' => 10000,
            'paid_minor' => 0,
            'status' => 'pending',
        ];
    }
}
