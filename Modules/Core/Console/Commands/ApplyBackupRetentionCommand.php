<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Concerns\RecordsScheduledTaskRun;
use Modules\Core\Domain\Actions\Backups\ApplyBackupRetentionAction;

/**
 * `php artisan serp:apply-backup-retention` (Book A CORE-13 §3/
 * BR-CORE-13-005). Scheduled daily, shortly after `serp:create-backup`
 * — expiring old backups right after a fresh one lands rather than
 * leaving a gap where none of a given bucket is retained.
 */
final class ApplyBackupRetentionCommand extends Command
{
    use RecordsScheduledTaskRun;

    protected $signature = 'serp:apply-backup-retention';

    protected $description = 'Expire backups outside the grandfather-father-son retention policy.';

    public function handle(ApplyBackupRetentionAction $action): int
    {
        $this->recordScheduledTaskRun('core.apply_backup_retention', function () use ($action): string {
            $expired = $action->execute();

            $this->info(count($expired).' backup(s) expired.');

            return count($expired).' backup(s) expired.';
        });

        return self::SUCCESS;
    }
}
