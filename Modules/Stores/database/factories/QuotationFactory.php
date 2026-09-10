<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\Quotation;
use Modules\Stores\Models\QuotationRequest;
use Modules\Stores\Models\Supplier;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'quotation_request_id' => fn (array $attributes): int => QuotationRequest::factory()->create(['school_id' => $attributes['school_id']])->id,
            'supplier_id' => fn (array $attributes): int => Supplier::factory()->create(['school_id' => $attributes['school_id']])->id,
            'received_on' => now()->toDateString(),
            'subtotal_minor' => 100000,
            'tax_minor' => 15000,
            'total_minor' => 115000,
            'currency' => 'USD',
            'is_compliant' => true,
            'status' => 'received',
        ];
    }
}
