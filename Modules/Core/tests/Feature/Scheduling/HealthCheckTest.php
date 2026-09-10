<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Scheduling\RunHealthChecksAction;
use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\RunHealthChecksData;
use Modules\Core\Domain\Registry\HealthCheckRegistry;
use Modules\Core\Domain\Support\Scheduling\FailedJobsHealthCheck;
use Modules\Core\Domain\Support\Scheduling\NotificationFailureRateHealthCheck;
use Modules\Core\Domain\Support\Scheduling\OldestQueuedJobHealthCheck;
use Modules\Core\Domain\Support\Scheduling\PendingHealthCheck;
use Modules\Core\Domain\Support\Scheduling\QueueDepthHealthCheck;
use Modules\Core\Domain\Support\Scheduling\SchedulerLastRunHealthCheck;
use Modules\Core\Models\Notification;
use Modules\Core\Models\ScheduledTask;
use Modules\Core\Models\ScheduledTaskRun;
use Modules\Core\Models\School;
use Modules\Core\Models\SystemHealthCheck;

it('reports queue depth against the standard thresholds (Book A CORE-12 §3)', function (): void {
    expect((new QueueDepthHealthCheck)->run()->status)->toBe('healthy');

    for ($i = 0; $i < 105; $i++) {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
        ]);
    }

    expect((new QueueDepthHealthCheck)->run()->status)->toBe('degraded');
});

it('reports failed jobs in the last 24 hours', function (): void {
    expect((new FailedJobsHealthCheck)->run()->status)->toBe('healthy');

    foreach (range(1, 2) as $i) {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => now(),
        ]);
    }

    $result = (new FailedJobsHealthCheck)->run();
    expect($result->status)->toBe('degraded')
        ->and($result->value)->toBe('2');
});

it('ignores failed jobs older than 24 hours', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'boom',
        'failed_at' => now()->subDays(2),
    ]);

    expect((new FailedJobsHealthCheck)->run()->status)->toBe('healthy');
});

it('reports healthy with no jobs queued for the oldest-queued-job age check', function (): void {
    expect((new OldestQueuedJobHealthCheck)->run()->status)->toBe('healthy');
});

it('reports degraded once the oldest queued job has waited over a minute', function (): void {
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'available_at' => time(),
        'created_at' => time() - 120,
    ]);

    expect((new OldestQueuedJobHealthCheck)->run()->status)->toBe('degraded');
});

it('reports unhealthy on the scheduler heartbeat check when it has never run (AC-CORE-12-003)', function (): void {
    expect((new SchedulerLastRunHealthCheck)->run()->status)->toBe('unhealthy');
});

it('reports healthy on the scheduler heartbeat check when it ran recently', function (): void {
    $task = ScheduledTask::where('key', SchedulerLastRunHealthCheck::HEARTBEAT_TASK_KEY)->firstOrFail();
    ScheduledTaskRun::factory()->for($task, 'task')->create(['started_at' => now()->subSeconds(30)]);

    expect((new SchedulerLastRunHealthCheck)->run()->status)->toBe('healthy');
});

it('reports healthy for notification failure rate with no traffic in the last hour', function (): void {
    expect((new NotificationFailureRateHealthCheck)->run()->status)->toBe('healthy');
});

it('reports unhealthy for a notification failure rate over 10 percent', function (): void {
    $school = School::factory()->create();

    Notification::factory()->count(8)->create(['school_id' => $school->id, 'status' => 'sent', 'created_at' => now()]);
    Notification::factory()->count(2)->create(['school_id' => $school->id, 'status' => 'failed', 'created_at' => now()]);

    $result = (new NotificationFailureRateHealthCheck)->run();
    expect($result->status)->toBe('unhealthy')
        ->and($result->value)->toBe('20%');
});

it('runs every registered available check and upserts one row per key, skipping pending ones (Book A CORE-12 §3)', function (): void {
    app(RunHealthChecksAction::class)->execute(new RunHealthChecksData);

    expect(SystemHealthCheck::where('check_key', 'queue_depth')->exists())->toBeTrue()
        ->and(SystemHealthCheck::where('check_key', 'trial_balance_status')->exists())->toBeFalse();
});

it('alerts the vendor operations channel when a health check reports unhealthy (BR-CORE-12-009)', function (): void {
    app(RunHealthChecksAction::class)->execute(new RunHealthChecksData(checkKeys: ['scheduler_last_run']));

    $this->assertDatabaseHas('security_events', [
        'event_type' => 'health_check_unhealthy',
        'severity' => 'critical',
    ]);
});

it('upserts rather than appends on repeated runs of the same check', function (): void {
    app(RunHealthChecksAction::class)->execute(new RunHealthChecksData(checkKeys: ['queue_depth']));
    app(RunHealthChecksAction::class)->execute(new RunHealthChecksData(checkKeys: ['queue_depth']));

    expect(SystemHealthCheck::where('check_key', 'queue_depth')->count())->toBe(1);
});

it('skips a health check registered as not yet available', function (): void {
    HealthCheckRegistry::register(new PendingHealthCheck('throwaway_check', 'FIN-99'));

    app(RunHealthChecksAction::class)->execute(new RunHealthChecksData(checkKeys: ['throwaway_check']));

    expect(SystemHealthCheck::where('check_key', 'throwaway_check')->exists())->toBeFalse();
});

it('has the standard suite registered by the service provider', function (): void {
    $registered = array_keys(HealthCheckRegistry::all());

    expect($registered)->toContain('queue_depth', 'failed_jobs_24h', 'oldest_queued_job_age', 'database_connection', 'redis_connection', 'storage_free_space', 'scheduler_last_run', 'notification_failure_rate_1h', 'trial_balance_status');

    foreach (HealthCheckRegistry::all() as $check) {
        expect($check)->toBeInstanceOf(HealthCheck::class);
    }
});
