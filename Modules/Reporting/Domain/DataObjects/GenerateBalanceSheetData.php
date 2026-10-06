<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GenerateBalanceSheetData
{
    public function __construct(
        public int $schoolId,
        public CarbonInterface $asAt,
        public string $currency,
        public ?CarbonInterface $asKnownOn = null,
    ) {}
}
