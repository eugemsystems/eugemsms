<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Events;

use Modules\Reporting\Models\ReportSchedule;

final class ScheduledReportDelivered
{
    public function __construct(
        public readonly ReportSchedule $schedule,
    ) {}
}
