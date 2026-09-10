<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class TrialBalanceLine
{
    public function __construct(
        public int $accountId,
        public string $accountCode,
        public string $accountName,
        public string $currency,
        public int $debitMinor,
        public int $creditMinor,
    ) {}
}
