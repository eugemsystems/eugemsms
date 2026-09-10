<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Install\BackupProvider;
use Modules\Core\Domain\DataObjects\Install\UpgradeData;
use Modules\Core\Domain\DataObjects\Install\UpgradeResult;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Install\UpgradeStatus;
use Modules\Core\Models\SystemUpgrade;
use Throwable;

/**
 * ACT-RunUpgrade (Book A CORE-01 §3): pre-backup → migrate → verify →
 * record. BR-CORE-01-011: takes a full backup before running any
 * migration and records the backup reference; an upgrade with no
 * verified backup is refused.
 */
final class RunUpgradeAction extends Action
{
    public function __construct(
        private readonly BackupProvider $backupProvider,
    ) {}

    public function execute(UpgradeData $data): UpgradeResult
    {
        $backup = $this->backupProvider->backup();

        if (! $backup->verified) {
            throw new class('An upgrade with no verified backup is refused. '.($backup->message ?? '')) extends DomainException
            {
                public function errorCode(): string
                {
                    return 'UPGRADE_BACKUP_NOT_VERIFIED';
                }
            };
        }

        $fromVersion = (string) config('app.version', '1.0.0');

        $upgrade = SystemUpgrade::create([
            'from_version' => $fromVersion,
            'to_version' => $data->toVersion,
            'started_at' => now(),
            'status' => UpgradeStatus::Running,
            'backup_reference' => $backup->reference,
        ]);

        try {
            Artisan::call('migrate', ['--force' => true]);
            $ran = array_values(array_filter(explode("\n", Artisan::output())));

            $upgrade->update([
                'status' => UpgradeStatus::Completed,
                'completed_at' => now(),
                'migrations_run' => $ran,
            ]);

            return new UpgradeResult(
                status: UpgradeStatus::Completed,
                backupReference: $backup->reference,
                ranMigrations: $ran,
                message: "Upgraded from {$fromVersion} to {$data->toVersion}.",
            );
        } catch (Throwable $exception) {
            $upgrade->update([
                'status' => UpgradeStatus::Failed,
                'completed_at' => now(),
                'error_log' => $exception->getMessage(),
            ]);

            return new UpgradeResult(
                status: UpgradeStatus::Failed,
                backupReference: $backup->reference,
                ranMigrations: [],
                message: $exception->getMessage(),
            );
        }
    }
}
