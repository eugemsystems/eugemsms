<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Events;

use Modules\Utilities\Models\PrepaidTokenPurchase;

final class TokenUncredited
{
    public function __construct(
        public readonly PrepaidTokenPurchase $purchase,
        public readonly int $hoursUncredited,
    ) {}
}
