<?php

declare(strict_types=1);

namespace Modules\Fiscal\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Fiscal\Models\FiscalDevice;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * @extends Factory<FiscalReceipt>
 */
class FiscalReceiptFactory extends Factory
{
    protected $model = FiscalReceipt::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'device_id' => fn (array $attributes): int => FiscalDevice::factory()->create(['school_id' => $attributes['school_id']])->id,
            'source_type' => 'receipt',
            'source_id' => fake()->unique()->numberBetween(1, 1000000),
            'receipt_type' => 'fiscal_invoice',
            'receipt_currency' => 'USD',
            'receipt_counter' => fake()->unique()->numberBetween(1, 1000000),
            'global_counter' => fake()->unique()->numberBetween(1, 1000000),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'receipt_date' => now(),
            'total_minor' => 1000,
            'tax_breakdown' => [],
            'payment_methods' => ['cash'],
            'status' => 'queued',
            'payload' => [],
        ];
    }
}
