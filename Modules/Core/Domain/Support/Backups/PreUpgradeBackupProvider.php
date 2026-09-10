<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Backups;

use Modules\Core\Domain\Actions\Backups\CreateBackupAction;
use Modules\Core\Domain\Contracts\Install\BackupProvider;
use Modules\Core\Domain\Contracts\Install\BackupResult;
use Modules\Core\Domain\DataObjects\Backups\CreateBackupData;

/**
 * Book A CORE-13 §3/BR-CORE-13-006 — "a full backup is taken before
 * every upgrade and every migration, and its reference recorded on
 * `system_upgrades`." Now that CORE-13 exists, this replaces
 * `NullBackupProvider` as `BackupProvider`'s bound implementation
 * (`CoreServiceProvider::register()`): `RunUpgradeAction` (CORE-01)
 * already stores whatever reference it gets back on `system_upgrades.
 * backup_reference` — that placeholder wiring was built for exactly
 * this moment.
 */
final class PreUpgradeBackupProvider implements BackupProvider
{
    public function __construct(
        private readonly CreateBackupAction $createBackup,
    ) {}

    public function backup(): BackupResult
    {
        $backup = $this->createBackup->execute(new CreateBackupData(
            type: 'full',
            triggeredBy: 'pre_upgrade',
        ));

        return new BackupResult(
            verified: $backup->status === 'completed' || $backup->status === 'verified',
            reference: $backup->ulid,
            message: $backup->status === 'failed' ? $backup->verification_notes : null,
        );
    }
}
