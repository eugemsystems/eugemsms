<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\Journal;

final class JournalReversed
{
    public function __construct(
        public readonly Journal $original,
        public readonly Journal $reversal,
    ) {}
}
