<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class DutySlot
{
    public function __construct(
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
    ) {}
}
