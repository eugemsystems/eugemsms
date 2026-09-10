<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\ReceiptAllocation;

/**
 * @extends Factory<ReceiptAllocation>
 */
class ReceiptAllocationFactory extends Factory
{
    protected $model = ReceiptAllocation::class;

    public function definition(): array
    {
        return [
            'school_id' => fn (array $attrs): ?int => Receipt::query()->whereKey($attrs['receipt_id'])->value('school_id'),
            'receipt_id' => Receipt::factory(),
            'amount_minor' => 10000,
            'currency' => 'USD',
            'allocation_method' => 'auto_oldest',
            'allocated_at' => now(),
            'allocated_by' => User::factory(),
        ];
    }
}
