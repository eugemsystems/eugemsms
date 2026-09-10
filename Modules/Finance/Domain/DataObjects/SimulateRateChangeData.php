<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class SimulateRateChangeData
{
    public function __construct(
        public int $schoolId,
        public string $foreignCurrency,
        public string $proposedRate,
        public CarbonInterface $asAt,
    ) {}
}
