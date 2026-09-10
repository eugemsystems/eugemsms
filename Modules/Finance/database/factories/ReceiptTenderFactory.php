<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\ReceiptTender;

/**
 * @extends Factory<ReceiptTender>
 */
class ReceiptTenderFactory extends Factory
{
    protected $model = ReceiptTender::class;

    public function definition(): array
    {
        return [
            'school_id' => fn (array $attrs): ?int => Receipt::query()->whereKey($attrs['receipt_id'])->value('school_id'),
            'receipt_id' => Receipt::factory(),
            'tender_type' => 'cash',
            'amount_minor' => 10000,
            'currency' => 'USD',
            'is_cleared' => true,
        ];
    }
}
