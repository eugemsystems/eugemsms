<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

final class OpeningBalancesImported
{
    /**
     * @param  array<int, int>  $journalIds
     */
    public function __construct(
        public readonly int $schoolId,
        public readonly array $journalIds,
    ) {}
}
