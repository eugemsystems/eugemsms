<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateRecipeData
{
    /**
     * @param  array<int, RecipeIngredientInput>  $ingredients
     * @param  array<int, string>|null  $allergenFlags
     */
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $category,
        public int $baseServings,
        public array $ingredients = [],
        public ?array $allergenFlags = null,
        public bool $isVegetarian = false,
        public bool $isHalalSuitable = true,
    ) {}
}
