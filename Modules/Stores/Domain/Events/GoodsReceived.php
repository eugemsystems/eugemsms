<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\GoodsReceivedNote;

final class GoodsReceived
{
    public function __construct(
        public readonly GoodsReceivedNote $grn,
    ) {}
}
