<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Recipe;
use Modules\Core\Models\School;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'SADZA-'.fake()->unique()->numerify('###'),
            'name' => 'Sadza ne Nyama',
            'category' => 'staple',
            'base_servings' => 100,
            'is_vegetarian' => false,
            'is_halal_suitable' => true,
            'is_active' => true,
        ];
    }
}
