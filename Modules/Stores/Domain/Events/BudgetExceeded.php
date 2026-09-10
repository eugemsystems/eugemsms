<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\BudgetLine;

final class BudgetExceeded
{
    public function __construct(
        public readonly BudgetLine $budgetLine,
        public readonly int $requestedMinor,
        public readonly int $availableMinor,
    ) {}
}
