<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Actions\Install\RunUpgradeAction;
use Modules\Core\Domain\DataObjects\Install\UpgradeData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Install\UpgradeStatus;

/**
 * `php artisan serp:upgrade --to=1.1.0 --backup-first` (Book A CORE-01 §6).
 * `--backup-first` is accepted for CLI-ergonomics parity with the spec but
 * is not itself optional — BR-CORE-01-011 always requires a verified
 * backup before any migration runs, regardless of the flag.
 */
final class UpgradeCommand extends Command
{
    protected $signature = 'serp:upgrade {--to= : Target version} {--backup-first : Accepted for parity with the spec; a backup is always required}';

    protected $description = 'Run the sERP upgrade runner: pre-backup, migrate, verify, record.';

    public function handle(RunUpgradeAction $action): int
    {
        $toVersion = $this->option('to');

        if (! is_string($toVersion) || $toVersion === '') {
            $this->error('--to=version is required.');

            return self::FAILURE;
        }

        try {
            $result = $action->execute(new UpgradeData(toVersion: $toVersion));
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result->status === UpgradeStatus::Completed) {
            $this->info($result->message ?? 'Upgrade completed.');

            return self::SUCCESS;
        }

        $this->error($result->message ?? 'Upgrade failed.');

        return self::FAILURE;
    }
}
