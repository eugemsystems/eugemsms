<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Install;

/**
 * CORE-13 (Backup, Restore & DR) owns real backup infrastructure and
 * isn't built yet. `NullBackupProvider` reports no verified backup, which
 * correctly causes `RunUpgradeAction` to refuse the upgrade per
 * BR-CORE-01-011 rather than silently skipping the safety check.
 */
interface BackupProvider
{
    public function backup(): BackupResult;
}
