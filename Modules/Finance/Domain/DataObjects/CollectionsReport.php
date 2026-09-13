<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CollectionsReport
{
    /**
     * @param  array<int, CollectionsByDayRow>  $byDay
     * @param  array<int, TenderTotalRow>  $byTender
     * @param  array<int, CollectionsByCashierRow>  $byCashier
     */
    public function __construct(
        public array $byDay,
        public array $byTender,
        public array $byCashier,
    ) {}
}
