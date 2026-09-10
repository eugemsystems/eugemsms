<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\CustomReport;
use Modules\Intelligence\Models\CustomReportSchedule;

/**
 * ACT-ScheduleCustomReport (Book J INT-01 §2/BR-INT-01-010).
 */
final class ScheduleCustomReportAction extends Action
{
    /**
     * @param  array<int, array<string, mixed>>  $recipients
     */
    public function execute(int $reportId, string $frequency, array $recipients, string $format, ?Carbon $nextRunAt = null): CustomReportSchedule
    {
        $report = CustomReport::findOrFail($reportId);

        return $this->transaction(fn (): CustomReportSchedule => CustomReportSchedule::create([
            'school_id' => $report->school_id,
            'report_id' => $report->id,
            'frequency' => $frequency,
            'recipients' => $recipients,
            'format' => $format,
            'next_run_at' => $nextRunAt ?? Carbon::now()->addDay(),
            'is_active' => true,
        ]));
    }
}
