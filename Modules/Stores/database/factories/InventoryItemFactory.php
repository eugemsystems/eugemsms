<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'ITM-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Maize Meal',
            'base_unit' => 'kg',
            'purchase_unit' => 'bag',
            'purchase_conversion' => 50,
            'issue_unit' => 'kg',
            'issue_conversion' => 1,
            'is_perishable' => false,
            'requires_batch_tracking' => false,
            'is_high_risk' => true,
            'is_saleable' => false,
            'is_capitalisable' => false,
            'is_active' => true,
        ];
    }

    public function saleable(): self
    {
        return $this->state(fn (): array => [
            'is_saleable' => true,
            'sale_price_minor' => 1500,
            'sale_currency' => 'USD',
        ]);
    }

    public function capitalisable(): self
    {
        return $this->state(fn (): array => [
            'is_capitalisable' => true,
            'capitalisation_threshold_minor' => 50000,
        ]);
    }
}
