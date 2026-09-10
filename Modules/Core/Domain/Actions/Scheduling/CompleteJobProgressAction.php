<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Scheduling;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Scheduling\CompleteJobProgressData;
use Modules\Core\Models\JobProgress;

final class CompleteJobProgressAction extends Action
{
    public function execute(CompleteJobProgressData $data): JobProgress
    {
        $progress = JobProgress::findOrFail($data->jobProgressId);

        return $this->transaction(function () use ($progress, $data): JobProgress {
            $progress->update([
                'status' => 'completed',
                'completed_steps' => $progress->total_steps ?? $progress->completed_steps,
                'result' => $data->result,
                'completed_at' => Carbon::now(),
            ]);

            return $progress;
        });
    }
}
