<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\DiscountAward;

final class AwardRevoked
{
    public function __construct(
        public readonly DiscountAward $award,
    ) {}
}
