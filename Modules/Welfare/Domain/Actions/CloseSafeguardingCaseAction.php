<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\RecordSafeguardingAuditEntryData;
use Modules\Welfare\Domain\Events\CaseClosed;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * ACT-CloseSafeguardingCase (Book G BRD-08 §2/BR-BRD-08-020/022). The
 * row is never deleted — see `SafeguardingCase`'s own guard. Closure
 * is a named human's decision, recorded (BR-BRD-08-022's "the system
 * makes no automated decision about any child in this module").
 */
final class CloseSafeguardingCaseAction extends Action
{
    public function __construct(
        private readonly RecordSafeguardingAuditEntryAction $recordAudit,
    ) {}

    public function execute(int $caseId, int $closedByUserId, string $closureSummary): SafeguardingCase
    {
        $case = SafeguardingCase::findOrFail($caseId);

        return $this->transaction(function () use ($case, $closedByUserId, $closureSummary): SafeguardingCase {
            $case->update([
                'status' => 'closed',
                'closed_at' => Carbon::now(),
                'closed_by' => $closedByUserId,
                'closure_summary' => $closureSummary,
            ]);

            $this->recordAudit->execute(new RecordSafeguardingAuditEntryData(
                schoolId: $case->school_id,
                eventType: 'case_closed',
                userId: $closedByUserId,
                userRoleAtTime: 'safeguarding_lead',
                payload: ['case_id' => $case->id],
                caseId: $case->id,
            ));

            event(new CaseClosed($case));

            return $case;
        });
    }
}
