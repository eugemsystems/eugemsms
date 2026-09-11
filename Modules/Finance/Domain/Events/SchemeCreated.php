<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\DiscountScheme;

final class SchemeCreated
{
    public function __construct(
        public readonly DiscountScheme $scheme,
    ) {}
}
