<?php

use Modules\Core\Models\ScheduledTask;

it('backfills a task the registry defines but the table is missing, and drops a stale leftover row (closes the .ai/rules/commands.md sync gap)', function (): void {
    ScheduledTask::where('key', 'core.scheduler_heartbeat')->delete();
    ScheduledTask::factory()->create(['key' => 'core.leftover_from_a_removed_module']);

    $this->artisan('serp:sync-scheduled-tasks')->assertSuccessful();

    expect(ScheduledTask::where('key', 'core.scheduler_heartbeat')->exists())->toBeTrue()
        ->and(ScheduledTask::where('key', 'core.leftover_from_a_removed_module')->exists())->toBeFalse();
});

it('is safe to run twice in a row', function (): void {
    $this->artisan('serp:sync-scheduled-tasks')->assertSuccessful();
    $before = ScheduledTask::count();

    $this->artisan('serp:sync-scheduled-tasks')->assertSuccessful();

    // Not compared against count(ScheduledTaskRegistry::all()) here: that static registry is
    // shared process-wide and other test files (e.g. ModuleScheduledTasksTest) register their
    // own ad-hoc `test.*` keys into it without ever clearing them, so its live count can drift
    // from this test's own DB snapshot depending on worker/run order. The point of this test is
    // idempotency (same count before and after a second sync), which doesn't need that registry.
    expect(ScheduledTask::count())->toBe($before);
});
