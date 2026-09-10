<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Events;

use Modules\Transport\Models\Trip;

final class TripDeparted
{
    public function __construct(
        public readonly Trip $trip,
    ) {}
}
