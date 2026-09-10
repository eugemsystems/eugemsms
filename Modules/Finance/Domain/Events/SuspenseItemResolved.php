<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\SuspenseItem;

final class SuspenseItemResolved
{
    public function __construct(
        public readonly SuspenseItem $suspenseItem,
    ) {}
}
