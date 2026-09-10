<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Scheduling;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Scheduling\StartJobProgressData;
use Modules\Core\Models\JobProgress;

/**
 * ACT-StartJobProgress (Book A CORE-12 §2/BR-CORE-12-005). Creates the
 * row a long-running job's own progress screen polls for live counts.
 */
final class StartJobProgressAction extends Action
{
    public function execute(StartJobProgressData $data): JobProgress
    {
        return $this->transaction(fn (): JobProgress => JobProgress::create([
            'school_id' => $data->schoolId,
            'user_id' => $data->userId,
            'job_type' => $data->jobType,
            'title' => $data->title,
            'status' => 'running',
            'total_steps' => $data->totalSteps,
            'completed_steps' => 0,
            'started_at' => Carbon::now(),
        ]));
    }
}
