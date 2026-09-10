<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Events;

use Modules\Farm\Models\InternalTransfer;

/**
 * BR-OPS-03-008 ⭐⭐ (→ `BRD-04`). `Modules\Boarding`'s own catering
 * costing (`BRD-04`) reads kitchen-store stock lots directly through
 * `FIN-09` — the same lot this transfer creates — so there is nothing
 * further for this event to drive; it exists for anything that wants
 * to observe the transfer itself (reporting, notifications), matching
 * `LearnerAssignedToRoute`'s own "fires for real, nothing subscribes
 * yet" shape.
 */
final class ProduceTransferredToKitchen
{
    public function __construct(
        public readonly InternalTransfer $transfer,
    ) {}
}
