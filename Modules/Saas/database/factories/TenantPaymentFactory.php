<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Models\TenantInvoice;
use Modules\Saas\Models\TenantPayment;

/**
 * @extends Factory<TenantPayment>
 */
class TenantPaymentFactory extends Factory
{
    protected $model = TenantPayment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invoice_id' => TenantInvoice::factory(),
            'amount_minor' => 15000,
            'currency' => 'USD',
            'payment_method' => 'manual',
            'gateway_reference' => null,
            'received_at' => Carbon::now(),
        ];
    }
}
