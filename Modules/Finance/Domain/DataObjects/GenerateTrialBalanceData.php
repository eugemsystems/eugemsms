<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GenerateTrialBalanceData
{
    public function __construct(
        public int $schoolId,
        public CarbonInterface $asAt,
        public ?int $termId = null,
    ) {}
}
