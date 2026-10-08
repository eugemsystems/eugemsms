<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\CustomReport;

/**
 * ACT-DeleteCustomReport (Book J INT-01 §5 screen table, "report
 * delete"). A hard delete — reports are query definitions, not
 * financial or safeguarding records, so none of the append-only
 * rules apply. `custom_report_schedules` and `report_shares` both
 * declare a cascade-delete foreign key on `report_id` (their own
 * migrations), so deleting a report takes its schedules and shares
 * with it rather than orphaning them.
 */
final class DeleteCustomReportAction extends Action
{
    public function execute(int $reportId): void
    {
        $report = CustomReport::findOrFail($reportId);

        $this->transaction(function () use ($report): void {
            $report->delete();
        });
    }
}
