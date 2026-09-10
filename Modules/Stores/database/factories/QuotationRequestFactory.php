<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\PurchaseRequisition;
use Modules\Stores\Models\QuotationRequest;

/**
 * @extends Factory<QuotationRequest>
 */
class QuotationRequestFactory extends Factory
{
    protected $model = QuotationRequest::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'requisition_id' => fn (array $attributes): int => PurchaseRequisition::factory()->create(['school_id' => $attributes['school_id']])->id,
            'request_number' => 'RFQ/'.fake()->unique()->numberBetween(1000, 99999),
            'suppliers_invited' => [],
            'issued_on' => now()->toDateString(),
            'closes_on' => now()->addDays(7)->toDateString(),
            'status' => 'open',
        ];
    }
}
