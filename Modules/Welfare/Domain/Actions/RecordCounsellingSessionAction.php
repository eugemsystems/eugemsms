<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\RecordCounsellingSessionData;
use Modules\Welfare\Models\CounsellingSession;

/**
 * ACT-RecordCounsellingSession (Book G BRD-08 §2/BR-BRD-08-016). A
 * session with `risk_indicators_present = true` does not escalate
 * itself — the system prompts (this action's return value carries the
 * flag straight through for the caller to act on), and
 * `EscalateCounsellingSessionAction` is the counsellor's own,
 * separate, recorded decision.
 */
final class RecordCounsellingSessionAction extends Action
{
    public function execute(RecordCounsellingSessionData $data): CounsellingSession
    {
        return $this->transaction(fn (): CounsellingSession => CounsellingSession::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'counsellor_staff_id' => $data->counsellorStaffId,
            'session_at' => $data->sessionAt,
            'duration_minutes' => $data->durationMinutes,
            'session_type' => $data->sessionType,
            'referral_source' => $data->referralSource,
            'presenting_theme' => $data->presentingTheme,
            'session_notes' => $data->sessionNotes,
            'risk_indicators_present' => $data->riskIndicatorsPresent,
            'next_session_on' => $data->nextSessionOn?->toDateString(),
            'attended' => $data->attended,
        ]));
    }
}
