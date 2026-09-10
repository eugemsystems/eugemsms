<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    public function definition(): array
    {
        return [
            'school_id' => fn (array $attrs): ?int => Invoice::query()->whereKey($attrs['invoice_id'])->value('school_id'),
            'invoice_id' => Invoice::factory(),
            'line_number' => 1,
            'component_id' => FeeComponent::factory(),
            'description' => 'Tuition',
            'quantity' => 1,
            'gross_minor' => 10000,
            'net_minor' => 10000,
            'currency' => 'USD',
            'allocation_priority' => 100,
            'tax_category' => 'exempt',
        ];
    }
}
