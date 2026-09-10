<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\IssuableItem;
use Modules\Core\Models\School;

/**
 * @extends Factory<IssuableItem>
 */
class IssuableItemFactory extends Factory
{
    protected $model = IssuableItem::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'ITM-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Bed Sheet',
            'category' => 'linen',
            'inventory_item_id' => null,
            'is_returnable' => true,
            'is_launderable' => true,
            'replacement_cost_minor' => 500,
            'currency' => 'USD',
            'expected_lifespan_terms' => 6,
            'requires_tagging' => false,
        ];
    }
}
