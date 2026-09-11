<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CostOfGenerositySchemeSummary
{
    public function __construct(
        public int $schemeId,
        public string $schemeCode,
        public string $schemeName,
        public int $grossMinor,
        public int $discountMinor,
        public int $netMinor,
        public string $currency,
    ) {}
}
