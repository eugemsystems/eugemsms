<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class OpenRollCallData
{
    public function __construct(
        public int $rollCallPointId,
        public int $hostelId,
        public int $termId,
        public CarbonInterface $rollDate,
    ) {}
}
