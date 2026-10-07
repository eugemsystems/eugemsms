---
paths:
  - 'Modules/*/Console/Commands/*.php'
---

# Commands

## scheduled_tasks DB table staleness — FIXED, but only once a database runs the new migration/command
Root-caused (2026-09-13), fixed (2026-10-07). `ScheduledTaskRegistry` is an in-memory static array populated fresh by every module's `ServiceProvider::boot()` on every request — but `StartScheduledTaskRunAction` (which every `RecordsScheduledTaskRun`-wrapped command calls) checks the **`scheduled_tasks` DATABASE table** (`ScheduledTask::where('key', ...)`), not the in-memory registry. The table used to be populated only by `Modules/Core/database/migrations/0016_01_01_000005_sync_scheduled_tasks.php` — a ONE-TIME migration near the very start of the migration history — so every task a module registered afterwards was never written to the table on an already-migrated database (confirmed empirically on a long-lived dev DB: only 3 stale rows against the dozens the registry now defines).

**The fix, three parts:**
1. `Modules/Core/database/migrations/0177_01_01_000001_resync_scheduled_tasks.php` re-runs the same sync once more, now that every module through Book K has registered its tasks — backfills any already-migrated database (run `php artisan migrate`).
2. `php artisan serp:sync-scheduled-tasks` (`SyncScheduledTasksCommand`, mirroring `serp:sync-permissions`) — safe to run any time, upserts by key and drops stale rows. Use this against a database that's already past migration 0177 but still missing a newer module's tasks (e.g. one restored from an older backup).
3. `RunUpgradeAction` now calls `ScheduledTaskRegistry::syncToDatabase()` itself, right after the permission-catalogue sync, so every future upgrade stays in sync automatically — no per-deploy manual step needed going forward.

**Before this fix landed**, don't invoke a `RecordsScheduledTaskRun`-wrapped command directly (as a CLI test, from a seeder, or via `$this->call()`) on a database that predates migration 0177 — it throws `UnregisteredScheduledTaskException` for any task registered after that database's own last scheduled-task sync. Call the underlying domain Action instead (e.g. `app(CheckPaymentPlanBreachesAction::class)->execute()`), or run `php artisan serp:sync-scheduled-tasks` first. A database created fresh (tests, a new install) is never affected — migrations run in order against the full, current registry.
