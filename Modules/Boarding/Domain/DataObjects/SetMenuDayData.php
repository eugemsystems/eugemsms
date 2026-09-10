<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class SetMenuDayData
{
    /**
     * @param  array<int, int>  $recipeIds
     */
    public function __construct(
        public int $cycleId,
        public int $cycleDay,
        public string $meal,
        public array $recipeIds,
        public ?string $notes = null,
    ) {}
}
