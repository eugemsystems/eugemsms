<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

/**
 * BR-FIN-01-023: a balance query always takes a currency. "The balance"
 * without one is meaningless in this system.
 */
final readonly class CalculateAccountBalanceData
{
    public function __construct(
        public int $accountId,
        public string $currency,
        public CarbonInterface $asAt,
    ) {}
}
