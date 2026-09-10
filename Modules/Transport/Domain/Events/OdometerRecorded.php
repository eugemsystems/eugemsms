<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Events;

use Modules\Transport\Models\Vehicle;

/**
 * BR-OPS-01-012 ⭐/AC-OPS-01-005. `Modules\Operations`'s own
 * `CheckUsageBasedMaintenanceAction` (odometer side) is called
 * directly by `RecordTripOdometerAction` — real wiring, not a
 * deferral. `FIN-10`'s units-of-production depreciation is the
 * deliberate deferral here: `PreviewDepreciationRunAction` (Book H1,
 * already shipped and gated) always calls
 * `DepreciationCalculator::monthlyCharge()` with its default
 * `$unitsConsumedThisPeriod = 0.0` and isn't yet subscribed to this
 * event — the same "don't unsupervised-edit an already-gated earlier
 * book's core action" boundary `Modules\Stores`' own capitalisation
 * listeners document.
 */
final class OdometerRecorded
{
    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly float $kmDelta,
    ) {}
}
