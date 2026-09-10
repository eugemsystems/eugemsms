<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class ReverseJournalData
{
    public function __construct(
        public int $journalId,
        public string $reason,
        public int $reversedByUserId,
        public bool $overrideCrossPeriod = false,
    ) {}
}
