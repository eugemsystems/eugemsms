<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Actions\Install\VerifyRequirementsAction;
use Modules\Core\Domain\DataObjects\Install\RequirementCheckStatus;

/**
 * `php artisan serp:install:verify` (Book A CORE-01 §6) — requirements
 * only.
 */
final class InstallVerifyCommand extends Command
{
    protected $signature = 'serp:install:verify';

    protected $description = 'Check this server against the sERP installer requirements.';

    public function handle(VerifyRequirementsAction $action): int
    {
        $report = $action->execute();

        $rows = array_map(fn ($check) => [
            $check->name,
            match ($check->status) {
                RequirementCheckStatus::Pass => '<fg=green>PASS</>',
                RequirementCheckStatus::Warn => '<fg=yellow>WARN</>',
                RequirementCheckStatus::Fail => '<fg=red>FAIL</>',
            },
            $check->mandatory ? 'yes' : 'no',
            $check->message,
        ], $report->checks);

        $this->table(['Check', 'Status', 'Mandatory', 'Message'], $rows);

        if (! $report->passesMandatory()) {
            $this->error('Mandatory requirements are not met:');

            foreach ($report->failures() as $failure) {
                $this->line("  - {$failure->name}: {$failure->remediation}");
            }

            return self::FAILURE;
        }

        $this->info('All mandatory requirements are met.');

        return self::SUCCESS;
    }
}
