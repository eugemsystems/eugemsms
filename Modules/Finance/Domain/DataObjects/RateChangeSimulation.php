<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

/**
 * BR-FIN-06-013/AC-FIN-06-005: the impact simulation shown before a
 * proposed rate is approved.
 */
final readonly class RateChangeSimulation
{
    /**
     * @param  array<int, RevaluableAccountResult>  $accounts
     */
    public function __construct(
        public string $proposedRate,
        public int $totalDebtorsRecordedMinor,
        public int $totalDebtorsRevaluedMinor,
        public int $totalCreditorsRecordedMinor,
        public int $totalCreditorsRevaluedMinor,
        public int $fxResultMinor,
        public array $accounts,
    ) {}
}
