<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Scheduling;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Scheduling\CancelJobProgressData;
use Modules\Core\Models\JobProgress;

final class CancelJobProgressAction extends Action
{
    public function execute(CancelJobProgressData $data): JobProgress
    {
        $progress = JobProgress::findOrFail($data->jobProgressId);

        return $this->transaction(function () use ($progress): JobProgress {
            $progress->update([
                'status' => 'cancelled',
                'completed_at' => Carbon::now(),
            ]);

            return $progress;
        });
    }
}
