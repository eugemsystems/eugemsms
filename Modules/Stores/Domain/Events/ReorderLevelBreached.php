<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

final class ReorderLevelBreached
{
    public function __construct(
        public readonly Store $store,
        public readonly InventoryItem $item,
        public readonly float $onHand,
        public readonly float $reorderLevel,
    ) {}
}
