<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Models\PurchaseOrder;
use Modules\Stores\Models\Supplier;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'term_id' => fn (array $attributes): int => Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $attributes['academic_year_id']])->id,
            'po_number' => 'PO/'.fake()->unique()->numberBetween(1000, 99999),
            'supplier_id' => fn (array $attributes): int => Supplier::factory()->create(['school_id' => $attributes['school_id']])->id,
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'order_date' => now()->toDateString(),
            'subtotal_minor' => 100000,
            'tax_minor' => 15000,
            'total_minor' => 115000,
            'currency' => 'USD',
            'base_total_minor' => 115000,
            'committed_minor' => 0,
            'status' => 'draft',
        ];
    }
}
