<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Concerns\RecordsScheduledTaskRun;
use Modules\Core\Domain\Actions\Backups\CreateBackupAction;
use Modules\Core\Domain\DataObjects\Backups\CreateBackupData;
use RuntimeException;

/**
 * `php artisan serp:create-backup` (Book A CORE-13 §3/BR-CORE-13-001).
 * Scheduled daily in `routes/console.php` — `full` rather than just
 * `database`, since BR-CORE-13-001 only mandates "at least daily" for
 * the database half but a real production backup should cover
 * uploaded files too, and `full` is a strict superset.
 */
final class CreateScheduledBackupCommand extends Command
{
    use RecordsScheduledTaskRun;

    protected $signature = 'serp:create-backup {--type=full : database|files|full}';

    protected $description = 'Take a scheduled system backup.';

    public function handle(CreateBackupAction $action): int
    {
        $type = (string) $this->option('type');

        $this->recordScheduledTaskRun('core.create_backup', function () use ($action, $type): string {
            $backup = $action->execute(new CreateBackupData(type: $type, triggeredBy: 'schedule'));

            if ($backup->status !== 'completed') {
                throw new RuntimeException("Backup [{$backup->id}] ended in status [{$backup->status}]: {$backup->verification_notes}");
            }

            $this->info("Backup #{$backup->id} completed ({$backup->size_bytes} bytes).");

            return "Backup #{$backup->id} completed.";
        });

        return self::SUCCESS;
    }
}
