<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

final class TrialBalanceImbalanceDetected
{
    public function __construct(
        public readonly int $schoolId,
        public readonly string $currency,
        public readonly int $debitMinor,
        public readonly int $creditMinor,
    ) {}
}
