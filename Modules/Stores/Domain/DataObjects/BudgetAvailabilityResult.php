<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

final readonly class BudgetAvailabilityResult
{
    public function __construct(
        public string $result,
        public ?int $availableMinor,
    ) {}
}
