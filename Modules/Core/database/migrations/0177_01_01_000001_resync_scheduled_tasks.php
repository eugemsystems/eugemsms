<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Domain\Registry\ScheduledTaskRegistry;

/**
 * Re-runs `0016_01_01_000005_sync_scheduled_tasks.php` now that every
 * module through Book K has registered its own scheduled tasks. That
 * earlier migration only ever ran once, against whichever modules
 * existed at the time (Book A) — every task a later module registered
 * was never written to `scheduled_tasks` on an already-migrated
 * database, so `StartScheduledTaskRunAction` (which checks that table,
 * not the in-memory registry) throws `UnregisteredScheduledTaskException`
 * for it. See `.ai/rules/commands.md` for the full root cause. Re-syncing
 * a code-owned registry from a migration is safe — see
 * `0009_01_01_000010_sync_core05_setting_definitions.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        ScheduledTaskRegistry::syncToDatabase();
    }

    public function down(): void
    {
        // Deliberately no-op — see 0009_01_01_000010.
    }
};
