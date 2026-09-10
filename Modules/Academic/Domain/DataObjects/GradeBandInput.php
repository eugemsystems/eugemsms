<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class GradeBandInput
{
    public function __construct(
        public string $grade,
        public float $minPercent,
        public float $maxPercent,
        public bool $isPass = true,
        public ?float $points = null,
        public ?string $descriptor = null,
        public ?string $colour = null,
    ) {}
}
