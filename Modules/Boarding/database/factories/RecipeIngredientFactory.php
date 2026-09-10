<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Recipe;
use Modules\Boarding\Models\RecipeIngredient;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
{
    protected $model = RecipeIngredient::class;

    public function definition(): array
    {
        $recipe = Recipe::factory();

        return [
            'school_id' => $recipe,
            'recipe_id' => $recipe,
            'inventory_item_id' => fake()->numberBetween(1, 1000),
            'quantity' => '12.0000',
            'unit' => 'kg',
            'is_substitutable' => false,
            'wastage_allowance_pct' => '5.00',
        ];
    }
}
