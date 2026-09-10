<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class RecipeIngredientInput
{
    public function __construct(
        public int $inventoryItemId,
        public float $quantity,
        public string $unit,
        public float $wastageAllowancePct = 0.0,
        public bool $isSubstitutable = false,
    ) {}
}
