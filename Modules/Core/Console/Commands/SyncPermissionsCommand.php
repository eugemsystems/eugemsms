<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Actions\Install\SyncPermissionCatalogueAction;
use Modules\Core\Domain\DataObjects\Install\SyncPermissionCatalogueData;

/**
 * `php artisan serp:sync-permissions` (Book A Part 1.8). Writes every
 * permission every module registered via `PermissionRegistry` into the
 * `permissions` table. Safe to run any time — it upserts by name — and
 * also runs automatically as part of `RunUpgradeAction`.
 */
final class SyncPermissionsCommand extends Command
{
    protected $signature = 'serp:sync-permissions';

    protected $description = 'Sync every module\'s registered permission catalogue into the database.';

    public function handle(SyncPermissionCatalogueAction $action): int
    {
        $result = $action->execute(new SyncPermissionCatalogueData);

        $this->info("Permission catalogue synced: {$result->created} created, {$result->updated} updated, {$result->total} total.");

        return self::SUCCESS;
    }
}
