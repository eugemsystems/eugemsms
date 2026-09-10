<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Scheduling;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Scheduling\FailJobProgressData;
use Modules\Core\Models\JobProgress;

final class FailJobProgressAction extends Action
{
    public function execute(FailJobProgressData $data): JobProgress
    {
        $progress = JobProgress::findOrFail($data->jobProgressId);

        return $this->transaction(function () use ($progress, $data): JobProgress {
            $progress->update([
                'status' => 'failed',
                'error' => $data->error,
                'completed_at' => Carbon::now(),
            ]);

            return $progress;
        });
    }
}
