<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Scheduling;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Scheduling\UpdateJobProgressData;
use Modules\Core\Models\JobProgress;

/**
 * ACT-UpdateJobProgress (Book A CORE-12 §2/BR-CORE-12-005/AC-CORE-12-004).
 * Called repeatedly from inside a running job to advance the counters
 * the polling UI reads — never touches `status`, which the start/
 * complete/fail/cancel actions own exclusively.
 */
final class UpdateJobProgressAction extends Action
{
    public function execute(UpdateJobProgressData $data): JobProgress
    {
        $progress = JobProgress::findOrFail($data->jobProgressId);

        return $this->transaction(function () use ($progress, $data): JobProgress {
            $progress->update(array_filter([
                'completed_steps' => $data->completedSteps,
                'current_message' => $data->currentMessage,
            ], fn ($value): bool => $value !== null));

            return $progress;
        });
    }
}
