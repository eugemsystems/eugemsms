<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierInvoice;

/**
 * @extends Factory<SupplierInvoice>
 */
class SupplierInvoiceFactory extends Factory
{
    protected $model = SupplierInvoice::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'term_id' => fn (array $attributes): int => Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $attributes['academic_year_id']])->id,
            'supplier_id' => fn (array $attributes): int => Supplier::factory()->create(['school_id' => $attributes['school_id']])->id,
            'invoice_number' => 'INV-'.fake()->unique()->numberBetween(10000, 99999),
            'invoice_date' => now()->toDateString(),
            'received_on' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal_minor' => 100000,
            'tax_minor' => 15000,
            'total_minor' => 115000,
            'currency' => 'USD',
            'base_total_minor' => 115000,
            'net_payable_minor' => 115000,
            'balance_minor' => 115000,
            'match_status' => 'unmatched',
            'status' => 'received',
        ];
    }
}
