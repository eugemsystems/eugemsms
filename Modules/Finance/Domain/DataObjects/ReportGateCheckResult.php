<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class ReportGateCheckResult
{
    public function __construct(
        public bool $isWithheld,
        public int $gateBalanceMinor,
        public string $currency,
        public int $thresholdMinor,
        public bool $hasOverride,
    ) {}
}
