<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CaptureExchangeRateData
{
    public function __construct(
        public int $schoolId,
        public int $sourceId,
        public string $fromCurrency,
        public string $toCurrency,
        public string $rate,
        public CarbonInterface $effectiveFrom,
        public int $capturedByUserId,
        public ?string $notes = null,
    ) {}
}
