<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\SuspenseItem;

/**
 * @extends Factory<SuspenseItem>
 */
class SuspenseItemFactory extends Factory
{
    protected $model = SuspenseItem::class;

    public function definition(): array
    {
        return [
            'school_id' => fn (array $attrs): ?int => Receipt::query()->whereKey($attrs['receipt_id'])->value('school_id'),
            'receipt_id' => Receipt::factory(),
            'source' => 'bank_deposit',
            'amount_minor' => 10000,
            'currency' => 'USD',
            'deposit_date' => now()->toDateString(),
            'status' => 'unidentified',
        ];
    }
}
