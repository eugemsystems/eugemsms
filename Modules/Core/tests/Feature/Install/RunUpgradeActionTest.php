<?php

use Modules\Core\Domain\Actions\Install\RunUpgradeAction;
use Modules\Core\Domain\Contracts\Install\BackupProvider;
use Modules\Core\Domain\Contracts\Install\BackupResult;
use Modules\Core\Domain\DataObjects\Install\UpgradeData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Install\NullBackupProvider;
use Modules\Core\Domain\Support\Install\UpgradeStatus;
use Modules\Core\Models\Backup;
use Modules\Core\Models\SystemUpgrade;

it('refuses to upgrade when no backup provider is configured (BR-CORE-01-011)', function (): void {
    $this->app->bind(BackupProvider::class, NullBackupProvider::class);

    app(RunUpgradeAction::class)->execute(new UpgradeData(toVersion: '1.1.0'));
})->throws(DomainException::class);

it('does not create an upgrade record when the backup is not verified', function (): void {
    $this->app->bind(BackupProvider::class, NullBackupProvider::class);

    try {
        app(RunUpgradeAction::class)->execute(new UpgradeData(toVersion: '1.1.0'));
    } catch (DomainException) {
        // expected
    }

    expect(SystemUpgrade::count())->toBe(0);
});

it('takes a real full backup before upgrading by default now that CORE-13 exists (BR-CORE-13-006)', function (): void {
    $result = app(RunUpgradeAction::class)->execute(new UpgradeData(toVersion: '1.1.0'));

    expect($result->status)->toBe(UpgradeStatus::Completed)
        ->and($result->backupReference)->not->toBeNull();

    $backup = Backup::where('ulid', $result->backupReference)->sole();
    expect($backup->type)->toBe('full')
        ->and($backup->triggered_by)->toBe('pre_upgrade')
        ->and($backup->status)->toBe('completed');

    $upgrade = SystemUpgrade::sole();
    expect($upgrade->backup_reference)->toBe($backup->ulid);
});

it('runs the upgrade and records it once a backup is verified', function (): void {
    $this->app->bind(BackupProvider::class, fn () => new class implements BackupProvider
    {
        public function backup(): BackupResult
        {
            return new BackupResult(verified: true, reference: 's3://backups/pre-upgrade.sql');
        }
    });

    $result = app(RunUpgradeAction::class)->execute(new UpgradeData(toVersion: '1.1.0'));

    expect($result->status)->toBe(UpgradeStatus::Completed)
        ->and($result->backupReference)->toBe('s3://backups/pre-upgrade.sql')
        ->and(SystemUpgrade::count())->toBe(1);

    $record = SystemUpgrade::sole();
    expect($record->status)->toBe(UpgradeStatus::Completed)
        ->and($record->to_version)->toBe('1.1.0')
        ->and($record->backup_reference)->toBe('s3://backups/pre-upgrade.sql');
});
