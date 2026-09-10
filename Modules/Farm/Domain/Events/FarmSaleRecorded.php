<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Events;

use Modules\Farm\Models\FarmSale;

/**
 * BR-OPS-03-016 (→ `FIN-13`). `Modules\Fiscal`'s
 * `RouteFarmSaleListener` now subscribes to this — see that
 * listener's own docblock and `farm_sales`' migration docblock for
 * the "forward-reference column, now closed" history. `performedByUserId`
 * is carried here (not stored on `FarmSale` itself) purely so that
 * listener can open a fiscal day for real if none is already open —
 * `RecordFarmSaleAction` itself is unaffected; this is additive event
 * payload, not a behaviour change.
 */
final class FarmSaleRecorded
{
    public function __construct(
        public readonly FarmSale $sale,
        public readonly int $performedByUserId,
    ) {}
}
