<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Events;

use Modules\Utilities\Models\GeneratorRun;

/**
 * BR-OPS-04-011 ⭐/AC-OPS-04-007. `OPS-02`'s usage-based schedule
 * check is called for real from `StopGeneratorRunAction`
 * (`CheckUsageBasedMaintenanceAction`, the same generic action
 * `Modules\Transport`'s own odometer recording already uses). `FIN-10`
 * units-of-production stays a documented deferral — see
 * `Modules\Transport\Domain\Events\OdometerRecorded`'s own docblock
 * for why: `PreviewDepreciationRunAction` (Book H1, already shipped
 * and gated) isn't subscribed to either module's usage event.
 */
final class GeneratorRunRecorded
{
    public function __construct(
        public readonly GeneratorRun $run,
    ) {}
}
