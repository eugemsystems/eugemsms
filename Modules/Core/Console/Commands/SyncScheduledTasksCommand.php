<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Registry\ScheduledTaskRegistry;

/**
 * `php artisan serp:sync-scheduled-tasks`, mirroring `serp:sync-permissions`
 * (SyncPermissionsCommand). Writes every scheduled task every module
 * registered via `ScheduledTaskRegistry` into the `scheduled_tasks` table —
 * the table `StartScheduledTaskRunAction` actually checks, not the
 * in-memory registry a `ServiceProvider::boot()` repopulates on every
 * request. Safe to run any time — it upserts by key and drops stale rows —
 * and also runs automatically as part of `RunUpgradeAction`.
 *
 * Closes the gap `.ai/rules/commands.md` documents: the table was only
 * ever populated once, by an early Book A migration, so every scheduled
 * task a module has registered since then was never written to it on an
 * already-migrated database.
 */
final class SyncScheduledTasksCommand extends Command
{
    protected $signature = 'serp:sync-scheduled-tasks';

    protected $description = 'Sync every module\'s registered scheduled-task catalogue into the database.';

    public function handle(): int
    {
        ScheduledTaskRegistry::syncToDatabase();

        $this->info('Scheduled task catalogue synced: '.count(ScheduledTaskRegistry::all()).' tasks registered.');

        return self::SUCCESS;
    }
}
