<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\TenantInvoice;

/**
 * @extends Factory<TenantInvoice>
 */
class TenantInvoiceFactory extends Factory
{
    protected $model = TenantInvoice::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'invoice_number' => 'TINV/'.fake()->unique()->numerify('######'),
            'period_month' => Carbon::today()->format('Y-m'),
            'line_items' => [['description' => 'Subscription fee', 'amount_minor' => 15000]],
            'subtotal_minor' => 15000,
            'tax_minor' => 0,
            'total_minor' => 15000,
            'currency' => 'USD',
            'due_date' => Carbon::today()->addDays(14)->toDateString(),
            'status' => 'issued',
        ];
    }

    public function status(string $status): self
    {
        return $this->state(['status' => $status]);
    }
}
