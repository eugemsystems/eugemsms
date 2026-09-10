<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\Budget;

final class BudgetRevised
{
    public function __construct(
        public readonly Budget $previousVersion,
        public readonly Budget $newVersion,
    ) {}
}
