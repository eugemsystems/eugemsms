<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Stores\Models\SupplierInvoice;
use Modules\Stores\Models\SupplierInvoiceLine;

/**
 * @extends Factory<SupplierInvoiceLine>
 */
class SupplierInvoiceLineFactory extends Factory
{
    protected $model = SupplierInvoiceLine::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'invoice_id' => fn (array $attributes): int => SupplierInvoice::factory()->create(['school_id' => $attributes['school_id']])->id,
            'description' => 'Photocopy paper, A4, 80gsm',
            'quantity' => 20,
            'unit_price_minor' => 5000,
            'tax_category' => 'standard',
            'line_total_minor' => 100000,
            'expense_account_id' => fn (array $attributes): int => Account::factory()->expense()->create(['school_id' => $attributes['school_id']])->id,
        ];
    }
}
