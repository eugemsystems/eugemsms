<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\SupplierInvoice;
use Modules\Stores\Models\SupplierPayment;
use Modules\Stores\Models\SupplierPaymentAllocation;

/**
 * @extends Factory<SupplierPaymentAllocation>
 */
class SupplierPaymentAllocationFactory extends Factory
{
    protected $model = SupplierPaymentAllocation::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'payment_id' => fn (array $attributes): int => SupplierPayment::factory()->create(['school_id' => $attributes['school_id']])->id,
            'invoice_id' => fn (array $attributes): int => SupplierInvoice::factory()->create(['school_id' => $attributes['school_id']])->id,
            'amount_minor' => 115000,
            'currency' => 'USD',
            'allocated_at' => now(),
            'allocated_by' => User::factory(),
        ];
    }
}
