<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\GrnLine;

final class GoodsRejected
{
    public function __construct(
        public readonly GrnLine $grnLine,
    ) {}
}
