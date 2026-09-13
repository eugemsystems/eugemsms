<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class DailyBankingReport
{
    /**
     * @param  array<int, TenderTotalRow>  $tenderTotals
     */
    public function __construct(
        public array $tenderTotals,
        public int $receiptCount,
    ) {}
}
