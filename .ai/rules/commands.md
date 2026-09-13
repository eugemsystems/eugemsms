---
paths:
  - 'Modules/*/Console/Commands/*.php'
---

# Commands

## RecordsScheduledTaskRun-wrapped commands throw outside a full scheduler boot
Running a `serp:*` command that uses the `RecordsScheduledTaskRun` trait (e.g. `serp:check-payment-plan-breaches`) directly via `php artisan <command>` or `$this->call(...)` throws `Modules\Core\Domain\Exceptions\UnregisteredScheduledTaskException: No scheduled task is registered for key [...]`, even though the owning module's ServiceProvider does call `ScheduledTaskRegistry::register(...)` for that exact key in `boot()`. Confirmed pre-existing and NOT specific to any one command — reproduces standalone with no other code involved. Root cause not yet diagnosed (registry population timing vs. `StartScheduledTaskRunAction`'s own resolution, most likely).

**How to apply:** don't invoke a `RecordsScheduledTaskRun`-wrapped command directly (as a CLI test, from a seeder, or via `$this->call()` from another command) — call the underlying domain Action it wraps instead (e.g. `app(CheckPaymentPlanBreachesAction::class)->execute()` instead of `serp:check-payment-plan-breaches`). Until this is actually root-caused and fixed, treat every such command as broken outside a real scheduler run, and route around it the same way.

## scheduled_tasks DB table is stale — root cause of RecordsScheduledTaskRun failures
Root-caused (2026-09-13): `ScheduledTaskRegistry` is an in-memory static array populated fresh by every module's `ServiceProvider::boot()` on every request — but `StartScheduledTaskRunAction` (which every `RecordsScheduledTaskRun`-wrapped command calls) checks the **`scheduled_tasks` DATABASE table** (`ScheduledTask::where('key', ...)`), not the in-memory registry. The table is only ever populated by `Modules/Core/database/migrations/0016_01_01_000005_sync_scheduled_tasks.php` calling `ScheduledTaskRegistry::syncToDatabase()` — a ONE-TIME migration near the very start of the migration history. Every scheduled task any module has registered since then (`finance.check_payment_plan_breaches`, `finance.send_fee_reminders`, and almost certainly most others across the app) was never written to that table on an already-migrated dev database — confirmed empirically: this dev DB's `scheduled_tasks` table has only 3 stale rows (`core.scheduler_heartbeat` plus two apparent debug/test leftovers — `core.overdue.debug5`/`debug6`), not the dozens the registry now defines.

**Impact:** essentially every `RecordsScheduledTaskRun`-wrapped command in the app throws `UnregisteredScheduledTaskException` when actually run (CLI, seeder, or real cron) on this database — not just Finance's. This is a real, general platform gap, not a one-off bug — worth a proper fix (e.g. a `serp:sync-scheduled-tasks` command mirroring `serp:sync-permissions`, run post-deploy the same way) rather than a per-command workaround, but that fix is a deliberate scope decision for whoever picks it up next, not something to do silently as a side effect of an unrelated task.

**How to apply today:** same as before — call the underlying domain Action directly (e.g. `app(CheckPaymentPlanBreachesAction::class)->execute()`) instead of the wrapped `serp:*` command, until the sync gap is actually fixed.
