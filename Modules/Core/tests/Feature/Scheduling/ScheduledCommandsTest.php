<?php

use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Backups\CreateBackupAction;
use Modules\Core\Domain\DataObjects\Backups\CreateBackupData;
use Modules\Core\Domain\Support\Scheduling\SchedulerLastRunHealthCheck;
use Modules\Core\Models\Backup;
use Modules\Core\Models\RestoreTest;
use Modules\Core\Models\ScheduledTaskRun;
use Modules\Core\Models\School;
use Modules\Core\Models\SystemHealthCheck;

beforeEach(function (): void {
    Storage::fake('backups');
    Storage::fake('local');
});

it('records a heartbeat run', function (): void {
    $this->artisan('core:scheduler-heartbeat')->assertSuccessful();

    expect(ScheduledTaskRun::whereHas('task', fn ($q) => $q->where('key', SchedulerLastRunHealthCheck::HEARTBEAT_TASK_KEY))->where('status', 'completed')->exists())->toBeTrue();
});

it('runs health checks on a schedule and records the run', function (): void {
    $this->artisan('serp:run-health-checks')->assertSuccessful();

    expect(SystemHealthCheck::where('check_key', 'queue_depth')->exists())->toBeTrue()
        ->and(ScheduledTaskRun::whereHas('task', fn ($q) => $q->where('key', 'core.run_health_checks'))->where('status', 'completed')->exists())->toBeTrue();
});

it('runs integrity checks for every active school on a schedule', function (): void {
    $active = School::factory()->create(['status' => 'active']);
    $archived = School::factory()->create(['status' => 'archived']);

    $this->artisan('serp:run-integrity-checks')->assertSuccessful();

    $this->assertDatabaseHas('integrity_check_runs', ['school_id' => $active->id]);
    $this->assertDatabaseMissing('integrity_check_runs', ['school_id' => $archived->id]);
});

it('creates a scheduled backup and records the run', function (): void {
    $this->artisan('serp:create-backup')->assertSuccessful();

    $backup = Backup::where('triggered_by', 'schedule')->sole();
    expect($backup->status)->toBe('completed')
        ->and($backup->type)->toBe('full');

    expect(ScheduledTaskRun::whereHas('task', fn ($q) => $q->where('key', 'core.create_backup'))->where('status', 'completed')->exists())->toBeTrue();
});

it('applies backup retention on a schedule', function (): void {
    app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'manual'));

    $this->artisan('serp:apply-backup-retention')->assertSuccessful();

    expect(ScheduledTaskRun::whereHas('task', fn ($q) => $q->where('key', 'core.apply_backup_retention'))->where('status', 'completed')->exists())->toBeTrue();
});

it('runs a restore test against the latest system backup on a schedule', function (): void {
    app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'manual'));

    $this->artisan('serp:run-restore-test')->assertSuccessful();

    expect(RestoreTest::where('status', 'passed')->exists())->toBeTrue();
});

it('skips the restore test gracefully when no backup exists yet', function (): void {
    $this->artisan('serp:run-restore-test')->assertSuccessful();

    expect(RestoreTest::count())->toBe(0);
});

it('checks scheduled task freshness on a schedule', function (): void {
    $this->artisan('serp:check-scheduled-task-freshness')->assertSuccessful();

    expect(ScheduledTaskRun::whereHas('task', fn ($q) => $q->where('key', 'core.check_scheduled_task_freshness'))->where('status', 'completed')->exists())->toBeTrue();
});
