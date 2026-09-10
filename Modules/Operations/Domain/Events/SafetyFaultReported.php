<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Events;

use Modules\Operations\Models\FaultReport;

final class SafetyFaultReported
{
    public function __construct(
        public readonly FaultReport $report,
    ) {}
}
