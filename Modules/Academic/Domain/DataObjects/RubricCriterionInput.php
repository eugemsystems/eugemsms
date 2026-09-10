<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class RubricCriterionInput
{
    /**
     * @param  array<int, array{level: string, min_mark: int|float, descriptor: string}>  $performanceLevels
     */
    public function __construct(
        public string $criterion,
        public float $maxMark,
        public float $weightPercent,
        public array $performanceLevels,
        public ?string $description = null,
    ) {}
}
