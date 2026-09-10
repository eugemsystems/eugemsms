<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Models\FaultReport;

/**
 * ACT-RejectFaultReport (Book H2 OPS-02 §6/BR-OPS-02-003). One of the
 * three triage outcomes — a reason is always required, matching the
 * codebase's own "never auto-dismissed without a note" convention
 * (see `Modules\Stores\Domain\Actions\RecordAnomalyInvestigationAction`).
 */
final class RejectFaultReportAction extends Action
{
    public function execute(int $faultReportId, string $rejectionReason, int $triagedByUserId): FaultReport
    {
        $report = FaultReport::findOrFail($faultReportId);

        if ($report->status !== 'reported') {
            throw new InvalidStateTransitionException(
                "Fault report #{$report->id} must be reported to be triaged (currently {$report->status}).",
                ['fault_report_id' => $report->id, 'status' => $report->status],
            );
        }

        if (trim($rejectionReason) === '') {
            throw ValidationException::withMessages([
                'rejectionReason' => 'A reason is required to reject a fault report.',
            ]);
        }

        return $this->transaction(fn (): FaultReport => tap($report)->update([
            'status' => 'rejected',
            'triaged_by' => $triagedByUserId,
            'triage_note' => $rejectionReason,
        ]));
    }
}
