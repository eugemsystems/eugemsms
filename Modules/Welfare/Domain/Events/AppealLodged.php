<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\Appeal;

final class AppealLodged
{
    public function __construct(
        public readonly Appeal $appeal,
    ) {}
}
