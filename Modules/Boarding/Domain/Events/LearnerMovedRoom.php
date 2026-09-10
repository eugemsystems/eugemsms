<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\BedAllocation;

final class LearnerMovedRoom
{
    public function __construct(
        public readonly BedAllocation $previous,
        public readonly BedAllocation $current,
    ) {}
}
