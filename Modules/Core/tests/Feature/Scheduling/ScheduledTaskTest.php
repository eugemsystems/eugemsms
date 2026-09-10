<?php

use Modules\Core\Domain\Actions\Scheduling\CheckScheduledTaskFreshnessAction;
use Modules\Core\Domain\Actions\Scheduling\CompleteScheduledTaskRunAction;
use Modules\Core\Domain\Actions\Scheduling\StartScheduledTaskRunAction;
use Modules\Core\Domain\DataObjects\Scheduling\CompleteScheduledTaskRunData;
use Modules\Core\Domain\DataObjects\Scheduling\StartScheduledTaskRunData;
use Modules\Core\Domain\Exceptions\UnregisteredScheduledTaskException;
use Modules\Core\Domain\Registry\ScheduledTaskRegistry;
use Modules\Core\Models\ScheduledTask;
use Modules\Core\Models\ScheduledTaskRun;
use Modules\Core\Models\School;

it('syncs the code-owned scheduled task list into the database, including the heartbeat (BR-CORE-12-007)', function (): void {
    expect(ScheduledTask::where('key', 'core.scheduler_heartbeat')->exists())->toBeTrue();
});

it('starts and completes a scheduled task run, recording duration (Book A CORE-12 §2)', function (): void {
    $task = ScheduledTask::factory()->create(['key' => 'core.test_task']);

    $run = app(StartScheduledTaskRunAction::class)->execute(new StartScheduledTaskRunData(taskKey: 'core.test_task'));

    expect($run->status)->toBe('running')
        ->and($run->task_id)->toBe($task->id);

    $completed = app(CompleteScheduledTaskRunAction::class)->execute(new CompleteScheduledTaskRunData(
        runId: $run->id,
        status: 'completed',
        output: 'done',
    ));

    expect($completed->status)->toBe('completed')
        ->and($completed->duration_ms)->not->toBeNull()
        ->and($completed->completed_at)->not->toBeNull();
});

it('throws for an unregistered scheduled task key', function (): void {
    app(StartScheduledTaskRunAction::class)->execute(new StartScheduledTaskRunData(taskKey: 'nonexistent'));
})->throws(UnregisteredScheduledTaskException::class);

it('raises a critical security event when a failed run belongs to an alert_on_failure task (Book A CORE-12 §2)', function (): void {
    $task = ScheduledTask::factory()->create(['key' => 'core.alerting_task', 'alert_on_failure' => true]);
    $run = ScheduledTaskRun::factory()->for($task, 'task')->create(['status' => 'running', 'completed_at' => null]);

    app(CompleteScheduledTaskRunAction::class)->execute(new CompleteScheduledTaskRunData(
        runId: $run->id,
        status: 'failed',
        error: 'boom',
    ));

    $this->assertDatabaseHas('security_events', [
        'event_type' => 'scheduled_task_failed',
        'severity' => 'critical',
    ]);
});

it('does not alert when a failed run belongs to a task with alert_on_failure disabled', function (): void {
    $task = ScheduledTask::factory()->create(['key' => 'core.quiet_task', 'alert_on_failure' => false]);
    $run = ScheduledTaskRun::factory()->for($task, 'task')->create(['status' => 'running', 'completed_at' => null]);

    app(CompleteScheduledTaskRunAction::class)->execute(new CompleteScheduledTaskRunData(
        runId: $run->id,
        status: 'failed',
    ));

    $this->assertDatabaseMissing('security_events', ['event_type' => 'scheduled_task_failed']);
});

it('flags a task that has never run as stale (BR-CORE-12-007/AC-CORE-12-003)', function (): void {
    ScheduledTask::factory()->create([
        'key' => 'core.never_run',
        'is_enabled' => true,
        'alert_if_not_run_within_minutes' => 15,
    ]);

    $stale = app(CheckScheduledTaskFreshnessAction::class)->execute();

    expect(collect($stale)->pluck('key'))->toContain('core.never_run');
    $this->assertDatabaseHas('security_events', ['event_type' => 'scheduled_task_stale']);
});

it('flags a task whose last run is older than its alert window as stale', function (): void {
    $task = ScheduledTask::factory()->create([
        'key' => 'core.overdue',
        'is_enabled' => true,
        'alert_if_not_run_within_minutes' => 15,
    ]);
    ScheduledTaskRun::factory()->for($task, 'task')->create(['started_at' => now()->subMinutes(30)]);

    $stale = app(CheckScheduledTaskFreshnessAction::class)->execute();

    expect(collect($stale)->pluck('key'))->toContain('core.overdue');
});

it('does not flag a task whose last run is within its alert window (AC-CORE-12-003)', function (): void {
    $task = ScheduledTask::factory()->create([
        'key' => 'core.fresh',
        'is_enabled' => true,
        'alert_if_not_run_within_minutes' => 15,
    ]);
    ScheduledTaskRun::factory()->for($task, 'task')->create(['started_at' => now()->subMinutes(5)]);

    $stale = app(CheckScheduledTaskFreshnessAction::class)->execute();

    expect(collect($stale)->pluck('key'))->not->toContain('core.fresh');
});

it('runs a scheduled task once per active school when is_per_school (BR-CORE-12-006)', function (): void {
    ScheduledTask::factory()->create(['key' => 'core.per_school_task']);
    $schools = School::factory()->count(2)->create();

    foreach ($schools as $school) {
        app(StartScheduledTaskRunAction::class)->execute(new StartScheduledTaskRunData(
            taskKey: 'core.per_school_task',
            schoolId: $school->id,
        ));
    }

    expect(ScheduledTaskRun::where('status', 'running')->whereIn('school_id', $schools->pluck('id'))->count())->toBe(2);
});

it('has the code-registered heartbeat task available under its key (Book A CORE-12 §2)', function (): void {
    expect(ScheduledTaskRegistry::has('core.scheduler_heartbeat'))->toBeTrue()
        ->and(ScheduledTaskRegistry::get('core.scheduler_heartbeat')->moduleCode)->toBe('CORE-12');
});
