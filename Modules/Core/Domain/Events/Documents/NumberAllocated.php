<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Documents;

use Modules\Core\Models\AllocatedNumber;

final class NumberAllocated
{
    public function __construct(
        public readonly AllocatedNumber $allocatedNumber,
    ) {}
}
