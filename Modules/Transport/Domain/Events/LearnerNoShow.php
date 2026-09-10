<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Events;

use Modules\Transport\Models\TripPassenger;

final class LearnerNoShow
{
    public function __construct(
        public readonly TripPassenger $passenger,
    ) {}
}
