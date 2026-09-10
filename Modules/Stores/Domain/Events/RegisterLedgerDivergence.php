<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

final class RegisterLedgerDivergence
{
    public function __construct(
        public readonly int $schoolId,
        public readonly int $categoryId,
        public readonly int $registerTotalMinor,
        public readonly int $ledgerBalanceMinor,
    ) {}
}
