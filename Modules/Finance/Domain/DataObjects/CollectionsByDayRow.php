<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CollectionsByDayRow
{
    public function __construct(
        public string $date,
        public string $currency,
        public int $totalMinor,
    ) {}
}
