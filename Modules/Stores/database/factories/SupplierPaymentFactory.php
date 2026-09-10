<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierPayment;

/**
 * @extends Factory<SupplierPayment>
 */
class SupplierPaymentFactory extends Factory
{
    protected $model = SupplierPayment::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'payment_number' => 'PMT/'.fake()->unique()->numberBetween(1000, 99999),
            'supplier_id' => fn (array $attributes): int => Supplier::factory()->create(['school_id' => $attributes['school_id']])->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'gross_minor' => 115000,
            'net_minor' => 115000,
            'currency' => 'USD',
            'status' => 'draft',
        ];
    }
}
