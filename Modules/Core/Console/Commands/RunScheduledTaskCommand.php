<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Modules\Core\Console\Concerns\RecordsScheduledTaskRun;
use Modules\Core\Domain\Registry\ScheduledTaskHandlerRegistry;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use RuntimeException;
use Throwable;

/**
 * `php artisan serp:run-task {key}` — runs one registered per-school job for
 * every active school (Book A CORE-12). A school that throws never stops the
 * others; the run is recorded as failed at the end if any did.
 */
final class RunScheduledTaskCommand extends Command
{
    use RecordsScheduledTaskRun;

    protected $signature = 'serp:run-task {key : The scheduled task key}';

    protected $description = 'Run a registered per-school scheduled task for every active school.';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $global = ScheduledTaskHandlerRegistry::globalHandlerFor($key);

        if ($global !== null) {
            $this->recordScheduledTaskRun($key, function () use ($global): string {
                $summary = $global();
                $this->info($summary);

                return $summary;
            });

            return self::SUCCESS;
        }

        $handlerClass = ScheduledTaskHandlerRegistry::handlerFor($key);

        if ($handlerClass === null) {
            $this->error("No handler is registered for scheduled task [{$key}].");

            return self::FAILURE;
        }

        $this->recordScheduledTaskRun($key, function () use ($handlerClass): string {
            $summaries = [];
            $failures = [];

            foreach (School::query()->where('status', 'active')->get() as $school) {
                try {
                    SchoolContext::set($school);
                    $result = $handlerClass instanceof Closure ? $handlerClass($school) : app($handlerClass)->handle($school);

                    if ($result !== null && $result !== '') {
                        $summaries[] = "{$school->id}: {$result}";
                    }
                } catch (Throwable $exception) {
                    $failures[] = "{$school->id}: {$exception->getMessage()}";
                } finally {
                    SchoolContext::clear();
                }
            }

            if ($failures !== []) {
                throw new RuntimeException('Failed for school(s) '.implode('; ', $failures));
            }

            $this->info(implode("\n", $summaries));

            return $summaries === [] ? 'Nothing to do.' : implode('; ', $summaries);
        });

        return self::SUCCESS;
    }
}
