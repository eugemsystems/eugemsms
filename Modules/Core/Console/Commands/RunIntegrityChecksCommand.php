<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Console\Concerns\RecordsScheduledTaskRun;
use Modules\Core\Domain\Actions\Audit\RunIntegrityChecksAction;
use Modules\Core\Domain\DataObjects\Audit\RunIntegrityChecksData;
use Modules\Core\Models\IntegrityCheckRun;
use Modules\Core\Models\School;

/**
 * `php artisan serp:run-integrity-checks` (Book A CORE-08 §4/
 * BR-CORE-08-008). `RunIntegrityChecksAction` accepts a nullable
 * `schoolId`, but every existing caller (the `Audit\Integrity` screen,
 * its own tests) always passes a real one — looping over every active
 * school here, one call each, is the only exercised, provably-correct
 * calling shape rather than trusting an unexercised "null means every
 * school" path.
 */
final class RunIntegrityChecksCommand extends Command
{
    use RecordsScheduledTaskRun;

    protected $signature = 'serp:run-integrity-checks';

    protected $description = 'Run every registered, available integrity check for every active school.';

    public function handle(RunIntegrityChecksAction $action): int
    {
        $this->recordScheduledTaskRun('core.run_integrity_checks', function () use ($action): string {
            $totalFailed = 0;
            $totalRun = 0;

            foreach (School::query()->where('status', 'active')->get() as $school) {
                $runs = $action->execute(new RunIntegrityChecksData(schoolId: $school->id));
                $totalRun += count($runs);
                $totalFailed += collect($runs)->filter(fn (IntegrityCheckRun $run): bool => ! $run->passed())->count();
            }

            $this->info("{$totalFailed} of {$totalRun} check(s) failed across every active school.");

            return "{$totalRun} check(s) run, {$totalFailed} failed.";
        });

        return self::SUCCESS;
    }
}
