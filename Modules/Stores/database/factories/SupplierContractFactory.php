<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierContract;

/**
 * @extends Factory<SupplierContract>
 */
class SupplierContractFactory extends Factory
{
    protected $model = SupplierContract::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'supplier_id' => fn (array $attributes): int => Supplier::factory()->create(['school_id' => $attributes['school_id']])->id,
            'contract_number' => 'CTR/'.fake()->unique()->numberBetween(1000, 99999),
            'title' => 'Annual stationery supply agreement',
            'contract_type' => 'supply',
            'starts_on' => now()->toDateString(),
            'status' => 'active',
        ];
    }
}
