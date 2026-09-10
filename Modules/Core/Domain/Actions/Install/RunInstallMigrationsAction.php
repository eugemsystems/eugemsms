<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\MigrationResult;
use Throwable;

/**
 * ACT-RunInstallMigrations (Book A CORE-01 §3). Runs in module prefix
 * order (§0.5); a failure halts immediately, reports the failing
 * migration, and leaves prior migrations intact for resumption
 * (BR-CORE-01-006).
 */
final class RunInstallMigrationsAction extends Action
{
    protected bool $transactional = false;

    public function execute(): MigrationResult
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            return new MigrationResult(
                successful: false,
                ranMigrations: $this->ranMigrations(),
                failedMigration: $this->extractFailingMigration($exception->getMessage()),
                errorMessage: $exception->getMessage(),
            );
        }

        return new MigrationResult(successful: true, ranMigrations: $this->ranMigrations());
    }

    /**
     * @return array<int, string>
     */
    private function ranMigrations(): array
    {
        try {
            return Artisan::call('migrate:status', ['--pending' => false]) === 0
                ? array_values(array_filter(explode("\n", Artisan::output())))
                : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function extractFailingMigration(string $message): ?string
    {
        if (preg_match('/(\d{4}_\d{2}_\d{2}_\d{6}\S*)/', $message, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
