<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\BillingRun;

final class BillingRunCommitted
{
    public function __construct(
        public readonly BillingRun $run,
    ) {}
}
