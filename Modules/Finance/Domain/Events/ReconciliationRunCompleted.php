<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\ReconciliationRun;

final class ReconciliationRunCompleted
{
    public function __construct(
        public readonly ReconciliationRun $run,
    ) {}
}
