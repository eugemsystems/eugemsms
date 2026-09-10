<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\OpenSafeguardingCaseData;
use Modules\Welfare\Models\CounsellingSession;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * ACT-EscalateCounsellingSession (Book G BRD-08 §2/BR-BRD-08-016). The
 * counsellor's own decision, recorded by linking the session to the
 * new (or existing) case — never automatic.
 */
final class EscalateCounsellingSessionAction extends Action
{
    public function __construct(
        private readonly OpenSafeguardingCaseAction $openCase,
    ) {}

    public function execute(int $sessionId, int $leadStaffId, int $decidedByUserId, string $summary, string $riskLevel = 'medium'): SafeguardingCase
    {
        $session = CounsellingSession::findOrFail($sessionId);

        return $this->transaction(function () use ($session, $leadStaffId, $decidedByUserId, $summary, $riskLevel): SafeguardingCase {
            $case = $this->openCase->execute(new OpenSafeguardingCaseData(
                schoolId: $session->school_id,
                studentId: $session->student_id,
                leadStaffId: $leadStaffId,
                openedByUserId: $decidedByUserId,
                category: 'other',
                riskLevel: $riskLevel,
                summary: $summary,
            ));

            $session->update(['escalated_to_case_id' => $case->id]);

            return $case;
        });
    }
}
