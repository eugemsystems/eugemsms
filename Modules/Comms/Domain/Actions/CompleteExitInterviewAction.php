<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\ExitInterview;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CompleteExitInterview (Book I COM-08 §3/BR-COM-08-007).
 */
final class CompleteExitInterviewAction extends Action
{
    public function execute(
        int $exitInterviewId,
        string $primaryReason,
        string $responseSource,
        ?string $detail = null,
        ?bool $wouldRecommend = null,
    ): ExitInterview {
        return $this->transaction(function () use ($exitInterviewId, $primaryReason, $responseSource, $detail, $wouldRecommend): ExitInterview {
            $interview = ExitInterview::findOrFail($exitInterviewId);
            $interview->update([
                'primary_reason' => $primaryReason,
                'detail' => $detail,
                'would_recommend' => $wouldRecommend,
                'response_source' => $responseSource,
                'completed_at' => Carbon::now(),
            ]);

            return $interview;
        });
    }
}
