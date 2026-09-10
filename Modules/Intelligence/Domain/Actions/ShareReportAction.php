<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\CustomReport;
use Modules\Intelligence\Models\ReportShare;

/**
 * ACT-ShareReport (Book J INT-01 §2 ⭐/BR-INT-01-005). Sharing never
 * copies permission — the share record only names WHO may run the
 * report; WHAT they see when they do is re-evaluated fresh, every
 * time, by `ExecuteCustomReportAction` against the runner's own
 * permissions, never the sharer's.
 */
final class ShareReportAction extends Action
{
    public function execute(int $reportId, string $sharedWithType, int $sharedWithId, int $sharedByUserId, bool $canEdit = false): ReportShare
    {
        $report = CustomReport::findOrFail($reportId);

        return $this->transaction(fn (): ReportShare => ReportShare::updateOrCreate(
            ['school_id' => $report->school_id, 'report_id' => $report->id, 'shared_with_type' => $sharedWithType, 'shared_with_id' => $sharedWithId],
            ['can_edit' => $canEdit, 'shared_by' => $sharedByUserId],
        ));
    }
}
