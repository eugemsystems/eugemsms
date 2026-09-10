<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\MovementLogEntry;

final class UnauthorisedBoundaryCrossing
{
    public function __construct(
        public readonly MovementLogEntry $entry,
    ) {}
}
