<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\ExitInterview;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RequestExitInterview (Book I COM-08 §3/BR-COM-08-007). Creates
 * only a pending request — never forces a completion; either
 * `CompleteExitInterviewAction` or `DeclineExitInterviewAction` may
 * follow, on the family's own terms.
 */
final class RequestExitInterviewAction extends Action
{
    public function execute(int $schoolId, int $studentId, ?int $guardianId = null): ExitInterview
    {
        return $this->transaction(fn (): ExitInterview => ExitInterview::create([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'requested_at' => Carbon::now(),
        ]));
    }
}
