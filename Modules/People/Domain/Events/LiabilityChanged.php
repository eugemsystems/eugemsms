<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\FeeLiability;

final class LiabilityChanged
{
    public function __construct(
        public readonly FeeLiability $liability,
    ) {}
}
