<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Concerns\RecordsScheduledTaskRun;
use Modules\Core\Domain\Actions\Backups\RunRestoreTestAction;
use Modules\Core\Domain\DataObjects\Backups\RunRestoreTestData;
use Modules\Core\Models\Backup;
use RuntimeException;

/**
 * `php artisan serp:run-restore-test` (Book A CORE-13 §3 ⭐
 * BR-CORE-13-003). "A backup that has never been test-restored is not
 * a backup" — scheduled weekly against the most recent completed
 * system-scope backup in `routes/console.php`.
 */
final class RunRestoreTestCommand extends Command
{
    use RecordsScheduledTaskRun;

    protected $signature = 'serp:run-restore-test';

    protected $description = 'Restore-test the most recent completed system backup.';

    public function handle(RunRestoreTestAction $action): int
    {
        $backup = Backup::query()
            ->where('scope', 'system')
            ->whereIn('status', ['completed', 'verified'])
            ->orderByDesc('completed_at')
            ->first();

        if ($backup === null) {
            $this->warn('No completed system backup exists to restore-test yet.');

            return self::SUCCESS;
        }

        $this->recordScheduledTaskRun('core.run_restore_test', function () use ($action, $backup): string {
            $test = $action->execute(new RunRestoreTestData(backupId: $backup->id));

            $this->info("Restore test for backup #{$backup->id}: {$test->status}.");

            if (! $test->passed()) {
                throw new RuntimeException("Restore test failed: {$test->error}");
            }

            return "Restore test passed for backup #{$backup->id}.";
        });

        return self::SUCCESS;
    }
}
