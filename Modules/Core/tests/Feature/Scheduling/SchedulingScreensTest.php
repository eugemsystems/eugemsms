<?php

use App\Models\User;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\ArrayMaintenanceMode;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Core\Livewire\Scheduling\DemoData;
use Modules\Core\Livewire\Scheduling\FailedJobs;
use Modules\Core\Livewire\Scheduling\Health;
use Modules\Core\Livewire\Scheduling\Maintenance;
use Modules\Core\Livewire\Scheduling\Progress;
use Modules\Core\Livewire\Scheduling\TaskRuns;
use Modules\Core\Livewire\Scheduling\Tasks;
use Modules\Core\Models\JobProgress;
use Modules\Core\Models\ScheduledTask;
use Modules\Core\Models\ScheduledTaskRun;
use Modules\Core\Models\School;
use Modules\Core\Models\SystemHealthCheck;

function insertFailedJob(): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\TestJob']),
        'exception' => "Exception: Something went wrong\n#0 stack trace",
        'failed_at' => now(),
    ]);

    return $uuid;
}

beforeEach(function (): void {
    // Swapped for every test in this file, not just the maintenance-mode
    // one: `down`/`up` write a real (harmless) template stub to
    // storage/framework regardless of which MaintenanceMode is bound,
    // but binding the in-memory implementation up front guarantees no
    // test in this file can ever write the real storage/framework/down
    // flag file the actual dev site's own separate PHP-FPM process
    // checks — belt-and-braces alongside the afterEach below.
    app()->instance(MaintenanceMode::class, new ArrayMaintenanceMode);
});

afterEach(function (): void {
    // Always bring the (in-memory, test-only) app back up, even if an
    // assertion above failed mid-test — a maintenance-mode test must
    // never leave any lingering state for the next test in the run.
    Artisan::call('up');
});

it('lists scheduled tasks with their last run', function (): void {
    $task = ScheduledTask::factory()->create(['name' => 'Nightly Backup']);
    ScheduledTaskRun::factory()->create(['task_id' => $task->id, 'status' => 'completed']);

    Livewire::test(Tasks::class)->assertSee('Nightly Backup')->assertSee('Completed');
});

it('checks scheduled task freshness and toasts a summary', function (): void {
    ScheduledTask::factory()->create([
        'key' => 'stale_task',
        'is_enabled' => true,
        'alert_if_not_run_within_minutes' => 5,
    ]);

    Livewire::test(Tasks::class)->call('checkFreshness')->assertDispatched('toast');

    $this->assertDatabaseHas('security_events', ['event_type' => 'scheduled_task_stale']);
});

it('shows the run history for one scheduled task by its key', function (): void {
    $task = ScheduledTask::factory()->create(['key' => 'my_task', 'name' => 'My Task']);
    ScheduledTaskRun::factory()->create(['task_id' => $task->id, 'status' => 'failed', 'error' => 'Boom']);

    Livewire::test(TaskRuns::class, ['taskKey' => 'my_task'])
        ->assertSee('My Task')
        ->assertSee('Boom');
});

it('runs health checks and records the latest reading per key', function (): void {
    Livewire::test(Health::class)->call('runChecks')->assertDispatched('toast');

    expect(SystemHealthCheck::where('check_key', 'queue_depth')->exists())->toBeTrue();
});

it('lists, retries, and forgets a failed job', function (): void {
    $uuid = insertFailedJob();

    Livewire::test(FailedJobs::class)->assertSee('TestJob');

    Livewire::test(FailedJobs::class)->call('retry', $uuid);

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and(DB::table('jobs')->count())->toBe(1);

    $secondUuid = insertFailedJob();
    Livewire::test(FailedJobs::class)->call('forget', $secondUuid);

    expect(DB::table('failed_jobs')->where('uuid', $secondUuid)->exists())->toBeFalse();
});

it('retries a batch of selected failed jobs', function (): void {
    $first = insertFailedJob();
    $second = insertFailedJob();

    Livewire::test(FailedJobs::class)
        ->set('selected', [$first, $second])
        ->call('retrySelected')
        ->assertDispatched('toast');

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(2);
});

it('shows the current user\'s own jobs and lets them cancel one', function (): void {
    $user = User::factory()->create();
    $mine = JobProgress::factory()->create(['user_id' => $user->id, 'title' => 'My Export', 'status' => 'running']);
    $someoneElses = JobProgress::factory()->create(['title' => 'Someone Else\'s Job']);

    Livewire::actingAs($user)
        ->test(Progress::class)
        ->assertSee('My Export')
        ->assertDontSee('Someone Else\'s Job')
        ->call('cancel', $mine->id)
        ->assertDispatched('toast');

    expect($mine->fresh()->status)->toBe('cancelled');
});

it('refuses to let a user cancel someone else\'s job', function (): void {
    $user = User::factory()->create();
    $someoneElses = JobProgress::factory()->create();

    Livewire::actingAs($user)
        ->test(Progress::class)
        ->call('cancel', $someoneElses->id);
})->throws(ModelNotFoundException::class);

it('toggles maintenance mode on and off with a bypass secret', function (): void {
    $component = Livewire::test(Maintenance::class)
        ->assertSet('isDown', false)
        ->call('enable')
        ->assertSet('isDown', true)
        ->assertDispatched('toast');

    expect($component->get('secret'))->not->toBeNull()
        ->and(app()->maintenanceMode()->active())->toBeTrue();

    $component->call('disable')->assertSet('isDown', false);

    expect(app()->maintenanceMode()->active())->toBeFalse();
});

it('runs the finance school-setup seeder from the demo data screen', function (): void {
    Livewire::test(DemoData::class)
        ->set('code', 'DDTEST')
        ->set('students', 12)
        ->call('run', 'finance-school-setup')
        ->assertSet('lastRanOk', true);

    expect(School::withoutGlobalScopes()->where('code', 'DDTEST')->exists())->toBeTrue();
})->group('slow');

it('refuses to run a seeder outside local/staging/testing', function (): void {
    app()['env'] = 'production';

    try {
        Livewire::test(DemoData::class)->assertForbidden();
    } finally {
        app()['env'] = 'testing';
    }
});
