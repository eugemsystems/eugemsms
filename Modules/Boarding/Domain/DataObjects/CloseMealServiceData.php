<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CloseMealServiceData
{
    public function __construct(
        public int $mealServiceId,
        public int $actualServed,
        public ?string $wastageNote = null,
    ) {}
}
