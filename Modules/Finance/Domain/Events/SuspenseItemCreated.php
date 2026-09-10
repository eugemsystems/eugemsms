<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\SuspenseItem;

final class SuspenseItemCreated
{
    public function __construct(
        public readonly SuspenseItem $suspenseItem,
    ) {}
}
