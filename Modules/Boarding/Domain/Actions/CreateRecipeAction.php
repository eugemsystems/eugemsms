<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateRecipeData;
use Modules\Boarding\Models\Recipe;
use Modules\Boarding\Models\RecipeIngredient;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateRecipe (Book F BRD-04 §2).
 */
final class CreateRecipeAction extends Action
{
    public function execute(CreateRecipeData $data): Recipe
    {
        return $this->transaction(function () use ($data): Recipe {
            $recipe = Recipe::create([
                'school_id' => $data->schoolId,
                'code' => $data->code,
                'name' => $data->name,
                'category' => $data->category,
                'base_servings' => $data->baseServings,
                'allergen_flags' => $data->allergenFlags,
                'is_vegetarian' => $data->isVegetarian,
                'is_halal_suitable' => $data->isHalalSuitable,
                'is_active' => true,
            ]);

            foreach ($data->ingredients as $ingredient) {
                RecipeIngredient::create([
                    'school_id' => $data->schoolId,
                    'recipe_id' => $recipe->id,
                    'inventory_item_id' => $ingredient->inventoryItemId,
                    'quantity' => $ingredient->quantity,
                    'unit' => $ingredient->unit,
                    'is_substitutable' => $ingredient->isSubstitutable,
                    'wastage_allowance_pct' => $ingredient->wastageAllowancePct,
                ]);
            }

            return $recipe;
        });
    }
}
