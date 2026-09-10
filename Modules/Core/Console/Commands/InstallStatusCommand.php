<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Support\Install\InstallProgress;
use Modules\Core\Domain\Support\Install\InstallStepKey;

/**
 * `php artisan serp:install:status` (Book A CORE-01 §6) — step-by-step
 * state.
 */
final class InstallStatusCommand extends Command
{
    protected $signature = 'serp:install:status';

    protected $description = 'Show the sERP installer\'s step-by-step progress.';

    public function handle(InstallProgress $progress): int
    {
        if (InstallProgress::isInstalled()) {
            $this->info('This platform is already installed.');

            return self::SUCCESS;
        }

        $rows = array_map(
            fn (InstallStepKey $key) => [$key->value, $key->label(), $progress->statusOf($key)->value],
            InstallStepKey::ordered(),
        );

        $this->table(['Key', 'Step', 'Status'], $rows);
        $this->line('Resumes at: <fg=cyan>'.$progress->resumeStep()->label().'</>');

        return self::SUCCESS;
    }
}
