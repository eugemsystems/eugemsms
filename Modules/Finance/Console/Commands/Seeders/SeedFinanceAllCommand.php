<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands\Seeders;

use Illuminate\Console\Command;

/**
 * Runs every `serp:seed:finance-*` command in the only order that
 * works — each step depends on rows the previous one created (a school
 * to attach users to, students to bill, invoices to receipt against).
 * See `Modules/Core/Livewire/Scheduling/DemoData.php` for the same
 * list surfaced as individual "Run" buttons in the admin UI.
 */
final class SeedFinanceAllCommand extends Command
{
    protected $signature = 'serp:seed:finance-all {--code=FINDEMO : Unique school code for the demo school} {--students=130 : How many students to enrol}';

    protected $description = 'Run every serp:seed:finance-* command in order, building the complete Finance demo dataset from scratch.';

    public function handle(): int
    {
        $code = (string) $this->option('code');

        $steps = [
            ['serp:seed:finance-school-setup', ['--code' => $code, '--students' => $this->option('students')]],
            ['serp:seed:finance-users', ['--code' => $code]],
            ['serp:seed:finance-ledger', ['--code' => $code]],
            ['serp:seed:finance-currency', ['--code' => $code]],
            ['serp:seed:finance-fees', ['--code' => $code]],
            ['serp:seed:finance-debtors', ['--code' => $code]],
            ['serp:seed:finance-till', ['--code' => $code]],
        ];

        foreach ($steps as [$command, $arguments]) {
            $this->line("<fg=cyan>▶ {$command}</>");

            $exitCode = $this->call($command, $arguments);

            if ($exitCode !== self::SUCCESS) {
                $this->components->error("{$command} failed — stopping the run.");

                return self::FAILURE;
            }
        }

        $this->components->info('The complete Finance demo dataset is ready.');

        return self::SUCCESS;
    }
}
