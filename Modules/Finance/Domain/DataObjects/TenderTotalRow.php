<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class TenderTotalRow
{
    public function __construct(
        public string $tenderType,
        public string $currency,
        public int $totalMinor,
    ) {}
}
