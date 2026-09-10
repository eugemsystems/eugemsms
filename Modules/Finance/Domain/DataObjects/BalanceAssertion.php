<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class BalanceAssertion
{
    public function __construct(
        public bool $balanced,
        public TrialBalance $trialBalance,
    ) {}
}
