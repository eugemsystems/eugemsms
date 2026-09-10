<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Support\Money;

/**
 * Book B FIN-06 §3 "realised FX". `obligationReliefAmount` is whatever
 * base-currency amount the obligation was actually relieved by (the
 * receipting flow's own rate, e.g. a school-set rate at the counter);
 * `tenderedAmount` is what was physically received, in its own
 * currency. The difference between what the bank side is *really*
 * worth at the settlement-date rate and what was relieved off the
 * obligation is the realised gain or loss — it is never absorbed.
 */
final readonly class CalculateRealisedFxData
{
    public function __construct(
        public int $schoolId,
        public Money $obligationReliefAmount,
        public Money $tenderedAmount,
        public CarbonInterface $settlementDate,
    ) {}
}
