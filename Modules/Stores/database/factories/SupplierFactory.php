<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\Supplier;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'SUP-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->company(),
            'supplier_type' => 'company',
            'is_vat_registered' => true,
            'preferred_currency' => 'USD',
            'payment_terms_days' => 30,
            'status' => 'active',
        ];
    }

    public function pendingApproval(): self
    {
        return $this->state(fn (): array => ['status' => 'pending_approval']);
    }

    public function blacklisted(): self
    {
        return $this->state(fn (): array => ['status' => 'blacklisted', 'blacklist_reason' => 'Persistent late/short delivery.']);
    }
}
