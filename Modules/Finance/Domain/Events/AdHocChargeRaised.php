<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\AdHocCharge;

final class AdHocChargeRaised
{
    public function __construct(
        public readonly AdHocCharge $charge,
    ) {}
}
