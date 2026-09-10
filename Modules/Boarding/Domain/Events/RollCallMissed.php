<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Carbon\CarbonInterface;
use Modules\Boarding\Models\RollCallPoint;

final class RollCallMissed
{
    public function __construct(
        public readonly RollCallPoint $point,
        public readonly int $hostelId,
        public readonly CarbonInterface $rollDate,
    ) {}
}
