<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierTaxClearance;

/**
 * @extends Factory<SupplierTaxClearance>
 */
class SupplierTaxClearanceFactory extends Factory
{
    protected $model = SupplierTaxClearance::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'supplier_id' => fn (array $attributes): int => Supplier::factory()->create(['school_id' => $attributes['school_id']])->id,
            'certificate_number' => 'ITF263-'.fake()->unique()->numberBetween(100000, 999999),
            'issued_on' => now()->subMonths(6)->toDateString(),
            'expires_on' => now()->addMonths(6)->toDateString(),
            'status' => 'valid',
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'issued_on' => now()->subYear()->toDateString(),
            'expires_on' => now()->subMonth()->toDateString(),
            'status' => 'expired',
        ]);
    }
}
