<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GenerateStatementData
{
    public function __construct(
        public int $schoolId,
        public string $subledgerType,
        public int $subledgerId,
        public string $currency,
        public CarbonInterface $from,
        public CarbonInterface $to,
    ) {}
}
