<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Scheduling;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordSecurityEventAction;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\DataObjects\Scheduling\CompleteScheduledTaskRunData;
use Modules\Core\Models\ScheduledTaskRun;

/**
 * ACT-CompleteScheduledTaskRun (Book A CORE-12 §2). A `failed` or
 * `timed_out` outcome on a task with `alert_on_failure` raises a
 * critical security event immediately — the same "don't wait for the
 * dashboard to be looked at" alerting `RunIntegrityChecksAction` uses
 * for a broken audit chain.
 */
final class CompleteScheduledTaskRunAction extends Action
{
    public function __construct(
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(CompleteScheduledTaskRunData $data): ScheduledTaskRun
    {
        $run = ScheduledTaskRun::with('task')->findOrFail($data->runId);

        $completedAt = Carbon::now();
        $durationMs = (int) round($run->started_at->diffInMilliseconds($completedAt, absolute: true));

        $this->transaction(function () use ($run, $data, $completedAt, $durationMs): void {
            $run->update([
                'status' => $data->status,
                'output' => $data->output,
                'error' => $data->error,
                'completed_at' => $completedAt,
                'duration_ms' => $durationMs,
            ]);
        });

        if (in_array($data->status, ['failed', 'timed_out'], true) && $run->task->alert_on_failure) {
            $this->recordSecurityEvent->execute(new RecordSecurityEventData(
                eventType: 'scheduled_task_failed',
                severity: 'critical',
                description: "Scheduled task [{$run->task->key}] {$data->status}.",
                schoolId: $run->school_id,
                context: ['task_key' => $run->task->key, 'run_id' => $run->id, 'error' => $data->error],
            ));
        }

        return $run->refresh();
    }
}
