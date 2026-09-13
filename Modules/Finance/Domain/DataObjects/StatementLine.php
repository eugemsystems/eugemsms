<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class StatementLine
{
    public function __construct(
        public string $effectiveAt,
        public string $journalNumber,
        public string $narration,
        public string $direction,
        public int $amountMinor,
        public int $runningBalanceMinor,
    ) {}
}
