<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CriterionMarkInput
{
    public function __construct(
        public string $criterion,
        public float $mark,
    ) {}
}
