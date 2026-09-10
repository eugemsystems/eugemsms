<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Models\FaultReport;

/**
 * ACT-MarkFaultReportDuplicate (Book H2 OPS-02 §6/BR-OPS-02-003). One
 * of the three triage outcomes — points at the report it duplicates
 * so the original stays traceable.
 */
final class MarkFaultReportDuplicateAction extends Action
{
    public function execute(int $faultReportId, int $duplicateOfReportId, int $triagedByUserId): FaultReport
    {
        $report = FaultReport::findOrFail($faultReportId);

        if ($report->status !== 'reported') {
            throw new InvalidStateTransitionException(
                "Fault report #{$report->id} must be reported to be triaged (currently {$report->status}).",
                ['fault_report_id' => $report->id, 'status' => $report->status],
            );
        }

        if ($duplicateOfReportId === $report->id) {
            throw ValidationException::withMessages([
                'duplicateOfReportId' => 'A fault report cannot be marked a duplicate of itself.',
            ]);
        }

        $original = FaultReport::where('school_id', $report->school_id)->findOrFail($duplicateOfReportId);

        return $this->transaction(fn (): FaultReport => tap($report)->update([
            'status' => 'duplicate',
            'triaged_by' => $triagedByUserId,
            'triage_note' => "Duplicate of fault report #{$original->id} ({$original->report_number}).",
        ]));
    }
}
