<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Scheduling;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Scheduling\StartScheduledTaskRunData;
use Modules\Core\Domain\Exceptions\UnregisteredScheduledTaskException;
use Modules\Core\Models\ScheduledTask;
use Modules\Core\Models\ScheduledTaskRun;

/**
 * ACT-StartScheduledTaskRun (Book A CORE-12 §2). Records the start of
 * one execution of a task named in `scheduled_tasks`; BR-CORE-12-006's
 * per-school fan-out is the caller's responsibility — one call per
 * active school, each carrying its own `schoolId`.
 */
final class StartScheduledTaskRunAction extends Action
{
    public function execute(StartScheduledTaskRunData $data): ScheduledTaskRun
    {
        $task = ScheduledTask::query()->where('key', $data->taskKey)->first();

        if ($task === null) {
            throw UnregisteredScheduledTaskException::forKey($data->taskKey);
        }

        return $this->transaction(fn (): ScheduledTaskRun => ScheduledTaskRun::create([
            'task_id' => $task->id,
            'school_id' => $data->schoolId,
            'status' => 'running',
            'started_at' => Carbon::now(),
        ]));
    }
}
