<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\ExitInterview;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-DeclineExitInterview (Book I COM-08 §3 ⭐/BR-COM-08-007
 * (AC-COM-08-005)). The decline itself IS the data — recorded exactly
 * like a completed one, distinguished only by `response_source`, so
 * it contributes to the school's overall response-rate reporting
 * rather than vanishing as a non-event.
 */
final class DeclineExitInterviewAction extends Action
{
    public function execute(int $exitInterviewId): ExitInterview
    {
        return $this->transaction(function () use ($exitInterviewId): ExitInterview {
            $interview = ExitInterview::findOrFail($exitInterviewId);
            $interview->update(['response_source' => 'declined', 'completed_at' => Carbon::now()]);

            return $interview;
        });
    }
}
